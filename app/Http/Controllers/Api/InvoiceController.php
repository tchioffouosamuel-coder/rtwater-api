<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    // GET /api/admin/invoices — admin
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with('user:id,name,email', 'order:id,status,total')
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->user_id, fn($q, $uid) => $q->where('user_id', $uid))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'       => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }

    // GET /api/admin/invoices/{id} — admin
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice->load('user', 'order.items.product')]);
    }

    // POST /api/admin/invoices — générer une facture depuis une commande
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'type'     => 'in:invoice,quote',
            'tax'      => 'nullable|numeric|min:0',
        ]);

        $order = Order::with('user', 'items.product')->findOrFail($validated['order_id']);

        // Éviter les doublons
        if (Invoice::where('order_id', $order->id)->where('type', $validated['type'] ?? 'invoice')->exists()) {
            return response()->json(['message' => 'Une facture existe déjà pour cette commande.'], 422);
        }

        $tax  = $validated['tax'] ?? 0;
        $type = $validated['type'] ?? 'invoice';

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id'  => $order->user_id,
            'number'   => Invoice::generateNumber($type),
            'type'     => $type,
            'status'   => 'draft',
            'subtotal' => $order->total,
            'tax'      => $tax,
            'total'    => $order->total + $tax,
        ]);

        return response()->json(['message' => 'Facture créée', 'data' => $invoice->load('user', 'order')], 201);
    }

    // GET /api/admin/invoices/{id}/pdf — télécharger la facture en PDF
    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load('user', 'order.items.product');

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
                  ->setPaper('a4');

        $filename = "{$invoice->number}.pdf";

        // Stocker le chemin PDF sur la facture pour référence future
        if (!$invoice->pdf_path) {
            $path = "invoices/{$filename}";
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, $pdf->output());
            $invoice->update(['pdf_path' => $path]);
        }

        return $pdf->download($filename);
    }

    // PUT /api/admin/invoices/{id} — changer le statut
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'status'  => 'required|in:draft,sent,paid,cancelled',
            'due_at'  => 'nullable|date',
        ]);

        if ($validated['status'] === 'sent' && !$invoice->sent_at) {
            $validated['sent_at'] = now();
        }

        $invoice->update($validated);

        return response()->json(['message' => 'Facture mise à jour', 'data' => $invoice->fresh()]);
    }
}

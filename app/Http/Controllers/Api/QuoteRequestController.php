<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteRequestController extends Controller
{
    // POST /api/quotes — public (formulaire site vitrine)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'phone'         => 'nullable|string|max:30',
            'company'       => 'nullable|string|max:255',
            'location'      => 'nullable|string|max:255',
            'solution_type' => 'nullable|string|max:255',
            'volume_m3'     => 'nullable|integer|min:1',
            'description'   => 'nullable|string|max:5000',
        ]);

        $quote = QuoteRequest::create($validated);

        return response()->json([
            'message' => 'Demande de devis reçue. Nous vous contacterons dans les plus brefs délais.',
            'data'    => ['id' => $quote->id],
        ], 201);
    }

    // GET /api/admin/quotes — admin
    public function index(Request $request): JsonResponse
    {
        $quotes = QuoteRequest::query()
            ->with('assignedTo:id,name,email')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('company', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'       => $quotes->items(),
            'pagination' => [
                'current_page' => $quotes->currentPage(),
                'last_page'    => $quotes->lastPage(),
                'total'        => $quotes->total(),
            ],
        ]);
    }

    // GET /api/admin/quotes/{id} — admin
    public function show(QuoteRequest $quoteRequest): JsonResponse
    {
        return response()->json(['data' => $quoteRequest->load('assignedTo:id,name', 'invoice')]);
    }

    // PUT /api/admin/quotes/{id} — admin (changer statut, assigner, notes)
    public function update(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'status'         => 'sometimes|in:new,in_progress,quoted,converted,rejected',
            'assigned_to'    => 'sometimes|nullable|exists:users,id',
            'internal_notes' => 'sometimes|nullable|string',
        ]);

        $quoteRequest->update($validated);

        return response()->json(['message' => 'Demande mise à jour', 'data' => $quoteRequest->fresh('assignedTo')]);
    }
}

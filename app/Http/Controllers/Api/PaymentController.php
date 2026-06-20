<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\SebpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private SebpayService $sebpay) {}

    /**
     * POST /api/orders/{order}/pay
     * Initiate a SEBPAY mobile money payment for a pending order.
     * Public route — no auth required (USSD push is self-authenticating).
     */
    public function initiate(Request $request, Order $order)
    {
        $request->validate([
            'operator' => ['required', 'string', 'in:Orange Money,MTN Money,Moov Money,Wave Money'],
            'phone'    => ['required', 'string', 'max:20'],
        ]);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Cette commande ne peut plus être payée (statut : ' . $order->status . ').',
            ], 422);
        }

        // Prevent duplicate pending payments
        $existing = $order->payments()
            ->whereIn('status', ['pending', 'success'])
            ->first();

        if ($existing && $existing->status === 'success') {
            return response()->json(['message' => 'Commande déjà payée.'], 422);
        }

        try {
            $result = $this->sebpay->initiatePayment([
                'amount'   => $order->total_price,
                'operator' => $request->operator,
                'phone'    => $request->phone,
            ]);

            $payment = DB::transaction(function () use ($order, $request, $result) {
                // Cancel any stale pending payment first
                $order->payments()->where('status', 'pending')->update(['status' => 'cancelled']);

                return Payment::create([
                    'order_id'              => $order->id,
                    'sebpay_transaction_id' => $result['transaction_id'] ?? null,
                    'operator'              => $request->operator,
                    'phone'                 => $request->phone,
                    'amount'                => $order->total_price,
                    'currency'              => config('services.sebpay.currency', 'XOF'),
                    'status'                => $result['status'] ?? 'pending',
                    'sebpay_response'       => $result,
                ]);
            });

            // If SEBPAY already confirms synchronously (rare but possible)
            if (($result['status'] ?? '') === 'success') {
                $order->update(['status' => 'paid']);
                $payment->update(['status' => 'success', 'confirmed_at' => now()]);
            }

            return response()->json([
                'payment'        => $payment,
                'transaction_id' => $result['transaction_id'] ?? null,
                'message'        => 'Paiement initié. Validez la demande sur votre téléphone.',
            ], 201);

        } catch (\RuntimeException $e) {
            Log::error('SEBPAY initiation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement : ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/webhooks/sebpay
     * Called by SEBPAY when a payment status changes.
     */
    public function webhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature  = $request->header('X-Sebpay-Signature', '');

        if (!$this->sebpay->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('SEBPAY webhook: invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();

        Log::info('SEBPAY webhook received', $data);

        $transactionId = $data['transaction_id'] ?? null;
        $status        = $data['status'] ?? null;

        if (!$transactionId || !$status) {
            return response()->json(['message' => 'Missing fields'], 400);
        }

        $payment = Payment::where('sebpay_transaction_id', $transactionId)->first();

        if (!$payment) {
            Log::warning('SEBPAY webhook: unknown transaction_id ' . $transactionId);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        DB::transaction(function () use ($payment, $status, $data) {
            $sebpayStatus = match ($status) {
                'success'  => 'success',
                'failed', 'error', 'rejected' => 'failed',
                'cancelled' => 'cancelled',
                default     => 'pending',
            };

            $payment->update([
                'status'          => $sebpayStatus,
                'sebpay_response' => array_merge($payment->sebpay_response ?? [], $data),
                'confirmed_at'    => $sebpayStatus === 'success' ? now() : null,
            ]);

            if ($sebpayStatus === 'success') {
                $payment->order->update(['status' => 'paid']);
            } elseif (in_array($sebpayStatus, ['failed', 'cancelled'])) {
                // Reset order to pending so user can retry
                $payment->order->update(['status' => 'pending']);
            }
        });

        return response()->json(['message' => 'OK']);
    }
}

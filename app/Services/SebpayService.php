<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class SebpayService
{
    private string $apiKey;
    private string $baseUrl;
    private string $country;
    private string $currency;

    public function __construct()
    {
        $this->apiKey   = config('services.sebpay.api_key', '');
        $this->baseUrl  = rtrim(config('services.sebpay.base_url', 'https://api.sebpay.com'), '/');
        $this->country  = config('services.sebpay.country', 'BJ');
        $this->currency = config('services.sebpay.currency', 'XOF');
    }

    /**
     * Initiate a mobile money payment.
     *
     * @param  array{amount:int|float, operator:string, phone:string}  $params
     * @return array{status:string, transaction_id:string, ...}
     *
     * @throws \RuntimeException
     */
    public function initiatePayment(array $params): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->post("{$this->baseUrl}/v1/payments", [
                    'amount'   => (int) $params['amount'],
                    'currency' => $this->currency,
                    'operator' => $params['operator'],
                    'phone'    => $params['phone'],
                    'country'  => $this->country,
                ]);

            if ($response->failed()) {
                $msg = $response->json('message') ?? $response->json('error') ?? 'SEBPAY error ' . $response->status();
                throw new \RuntimeException($msg, $response->status());
            }

            return $response->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('Impossible de contacter SEBPAY : ' . $e->getMessage(), 503, $e);
        }
    }

    /**
     * Check payment status by SEBPAY transaction ID.
     */
    public function checkStatus(string $transactionId): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/v1/payments/{$transactionId}");

            if ($response->failed()) {
                throw new \RuntimeException('SEBPAY status check failed: ' . $response->status());
            }

            return $response->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('Impossible de contacter SEBPAY : ' . $e->getMessage(), 503, $e);
        }
    }

    /**
     * Verify an incoming webhook signature.
     * SEBPAY sends: X-Sebpay-Signature: sha256=<hmac>
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool
    {
        $secret = config('services.sebpay.webhook_secret', '');
        if (empty($secret)) {
            return true; // skip verification if secret not configured yet
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}

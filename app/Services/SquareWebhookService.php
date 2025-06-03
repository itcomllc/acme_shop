<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Square Webhook Service
 */
class SquareWebhookService
{
    private string $webhookSecret;

    public function __construct()
    {
        $this->webhookSecret = config('square.webhook_secret');
    }

    /**
     * Verify Square webhook signature
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if (!$this->webhookSecret || !$signature) {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $this->webhookSecret, true));
        
        return hash_equals($expectedSignature, $signature);
    }
}

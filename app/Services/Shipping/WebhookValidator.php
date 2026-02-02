<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

/**
 * Validates webhook signatures from shipping providers.
 * Single Responsibility: Webhook security validation.
 */
class WebhookValidator
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    /**
     * Validate Andreani webhook signature.
     */
    public function validateAndreaniSignature(string $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            Log::warning('Andreani webhook received without signature');
            return false;
        }

        $expectedSignature = $this->generateAndreaniSignature($payload);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('Andreani webhook signature validation failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
        }

        return $isValid;
    }

    /**
     * Generate expected signature for Andreani webhook.
     */
    private function generateAndreaniSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Validate webhook signature using generic HMAC SHA256.
     */
    public function validateHmacSignature(string $payload, string $signature, string $algorithm = 'sha256'): bool
    {
        $expectedSignature = hash_hmac($algorithm, $payload, $this->secret);
        return hash_equals($expectedSignature, $signature);
    }
}

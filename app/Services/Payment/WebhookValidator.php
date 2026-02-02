<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @ai-context WebhookValidator handles webhook request validation.
 *             Following Single Responsibility Principle, this class is solely responsible
 *             for validating incoming webhook requests from payment gateways.
 */
class WebhookValidator
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService
    ) {}

    /**
     * Validate Mercado Pago webhook request.
     *
     * @param Request $request
     * @return bool
     */
    public function validateMercadoPagoWebhook(Request $request): bool
    {
        // Extract headers
        $headers = [
            'x-signature' => $request->header('x-signature'),
            'x-request-id' => $request->header('x-request-id'),
        ];

        // Get raw request body
        $rawBody = $request->getContent();

        try {
            $isValid = $this->mercadoPagoService->validateWebhookSignature($headers, $rawBody);

            if (!$isValid) {
                Log::warning('Mercado Pago webhook signature validation failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error validating Mercado Pago webhook', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use App\Services\Payment\WebhookValidator as PaymentWebhookValidator;
use App\Services\Shipping\ShippingService;
use App\Services\Shipping\WebhookValidator as ShippingWebhookValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @ai-context WebhookController handles external service webhooks.
 *             Single Responsibility: Receive and delegate webhook processing.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ShippingService $shippingService,
        private readonly PaymentWebhookValidator $paymentWebhookValidator
    ) {
    }

    /**
     * Handle Mercado Pago webhook notifications.
     * Validates the webhook signature and processes payment updates.
     */
    public function mercadoPago(Request $request): JsonResponse
    {
        Log::info('Received Mercado Pago webhook', [
            'ip' => $request->ip(),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
        ]);

        // Validate webhook signature for security
        if (!$this->paymentWebhookValidator->validateMercadoPagoWebhook($request)) {
            Log::warning('Mercado Pago webhook rejected - invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid signature'
            ], 401);
        }

        // Process the webhook
        try {
            $this->paymentService->processWebhook($request->all());

            Log::info('Mercado Pago webhook processed successfully');

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Mercado Pago webhook processing failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Mercado Pago from retrying
            // (we already logged the error for investigation)
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 200);
        }
    }

    /**
     * Handle Andreani shipping webhook notifications.
     */
    public function andreani(Request $request): JsonResponse
    {
        try {
            // Log incoming webhook
            Log::info('Andreani webhook received', [
                'ip' => $request->ip(),
                'data' => $request->all(),
            ]);

            // Validate webhook signature if secret is configured
            $secret = config('services.andreani.webhook_secret');
            if (!empty($secret)) {
                $validator = new ShippingWebhookValidator($secret);
                $signature = $request->header('X-Andreani-Signature');
                $payload = $request->getContent();

                if (!$validator->validateAndreaniSignature($payload, $signature)) {
                    Log::warning('Andreani webhook signature validation failed');

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid signature',
                    ], 401);
                }
            }

            // Process webhook
            $this->shippingService->processWebhook($request->all());

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Andreani webhook processing failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent excessive retries
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 200);
        }
    }
}

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
        // Log only non-sensitive webhook metadata
        Log::info('[Webhook] Received Mercado Pago webhook', [
            'ip' => $request->ip(),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'data_id' => $request->input('data.id'),
        ]);

        // Validate webhook signature for security
        if (!$this->paymentWebhookValidator->validateMercadoPagoWebhook($request)) {
            Log::warning('[Webhook] Mercado Pago webhook rejected - invalid signature', [
                'ip' => $request->ip(),
                'has_x_signature' => $request->hasHeader('x-signature'),
                'has_x_request_id' => $request->hasHeader('x-request-id'),
            ]);

            return response()->json([
                'error' => 'Invalid signature'
            ], 401);
        }

        // Process the webhook
        try {
            Log::info('[Webhook] Processing Mercado Pago webhook', [
                'type' => $request->input('type'),
                'data_id' => $request->input('data.id'),
            ]);

            $this->paymentService->processWebhook($request->all());

            Log::info('[Webhook] Mercado Pago webhook processed successfully', [
                'type' => $request->input('type'),
                'data_id' => $request->input('data.id'),
            ]);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('[Webhook] Mercado Pago webhook processing failed', [
                'type' => $request->input('type'),
                'data_id' => $request->input('data.id'),
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Return 200 to prevent Mercado Pago from retrying
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
            // Log only non-sensitive metadata
            Log::info('[Webhook] Andreani webhook received', [
                'ip' => $request->ip(),
                'shipment_id' => $request->input('shipmentId'),
                'status' => $request->input('status'),
            ]);

            // Validate webhook signature if secret is configured
            $secret = config('services.andreani.webhook_secret');
            if (!empty($secret)) {
                $validator = new ShippingWebhookValidator($secret);
                $signature = $request->header('X-Andreani-Signature');
                $payload = $request->getContent();

                if (!$validator->validateAndreaniSignature($payload, $signature)) {
                    Log::warning('[Webhook] Andreani webhook signature validation failed', [
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid signature',
                    ], 401);
                }
            }

            // Process webhook
            $this->shippingService->processWebhook($request->all());

            Log::info('[Webhook] Andreani webhook processed successfully', [
                'shipment_id' => $request->input('shipmentId'),
            ]);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('[Webhook] Andreani webhook processing failed', [
                'shipment_id' => $request->input('shipmentId'),
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Return 200 to prevent excessive retries
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 200);
        }
    }
}

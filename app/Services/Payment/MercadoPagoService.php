<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Exceptions\Domain\InvalidOperationException;
use App\Services\Payment\Net\PinnedMercadoPagoHttpClient;
use App\Services\Payment\DTOs\PaymentPreferenceRequest;
use App\Services\Payment\DTOs\PaymentPreferenceResponse;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

/**
 * @ai-context MercadoPagoService encapsulates all interactions with the Mercado Pago API.
 *             Following Single Responsibility Principle, this service is solely responsible
 *             for Mercado Pago SDK operations. It abstracts the complexity of the SDK
 *             and provides a clean interface for payment operations.
 */
class MercadoPagoService
{
    private PreferenceClient $preferenceClient;
    private PaymentClient $paymentClient;
    private string $accessToken;
    private ?string $webhookSecret;
    private bool $tlsPinningEnabled;
    private ?string $tlsPinnedPublicKey;

    private bool $initialized = false;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token') ?? '';
        $this->webhookSecret = config('services.mercadopago.webhook_secret');
        $this->tlsPinningEnabled = (bool) config('services.mercadopago.tls_pinning_enabled', false);
        $this->tlsPinnedPublicKey = config('services.mercadopago.tls_pinned_public_key');

        if (!empty($this->accessToken)) {
            $this->initialize();
        }
    }

    private function initialize(): void
    {
        $this->configureSDK();
        $this->configureTlsPinning();
        $this->preferenceClient = new PreferenceClient();
        $this->paymentClient = new PaymentClient();
        $this->initialized = true;
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            if (empty($this->accessToken)) {
                throw new InvalidOperationException(
                    'Mercado Pago access token is not configured',
                    'MERCADOPAGO_NOT_CONFIGURED'
                );
            }
            $this->initialize();
        }
    }

    /**
     * Configure the Mercado Pago SDK with authentication credentials.
     */
    private function configureSDK(): void
    {
        MercadoPagoConfig::setAccessToken($this->accessToken);
        $isLocalRuntime = app()->environment(['local', 'testing']);

        MercadoPagoConfig::setRuntimeEnviroment(
            $isLocalRuntime ? MercadoPagoConfig::LOCAL : MercadoPagoConfig::SERVER
        );
    }

    /**
     * Enable TLS certificate pinning for Mercado Pago API calls when configured.
     *
     * Expected pin format:
     *   sha256//BASE64_ENCODED_SPKI_HASH
     */
    private function configureTlsPinning(): void
    {
        if (!$this->tlsPinningEnabled) {
            return;
        }

        if (!defined('CURLOPT_PINNEDPUBLICKEY')) {
            throw new InvalidOperationException(
                'TLS pinning is enabled but CURLOPT_PINNEDPUBLICKEY is not supported in this cURL build',
                'MERCADOPAGO_TLS_PINNING_UNSUPPORTED'
            );
        }

        if (empty($this->tlsPinnedPublicKey)) {
            throw new InvalidOperationException(
                'TLS pinning is enabled but MERCADOPAGO_TLS_PINNED_PUBLIC_KEY is not configured',
                'MERCADOPAGO_TLS_PINNING_NOT_CONFIGURED'
            );
        }

        MercadoPagoConfig::setHttpClient(
            new PinnedMercadoPagoHttpClient($this->tlsPinnedPublicKey)
        );

        Log::info('Mercado Pago TLS pinning enabled');
    }

    /**
     * Create a payment preference in Mercado Pago.
     *
     * @param PaymentPreferenceRequest $request
     * @return PaymentPreferenceResponse
     * @throws InvalidOperationException
     */
    public function createPreference(PaymentPreferenceRequest $request): PaymentPreferenceResponse
    {
        $this->ensureInitialized();
        try {
            Log::info('Creating Mercado Pago preference', [
                'external_reference' => $request->externalReference,
                'items_count' => count($request->items),
            ]);

            $preference = $this->preferenceClient->create($request->toArray());

            Log::info('Mercado Pago preference created successfully', [
                'preference_id' => $preference->id,
                'external_reference' => $request->externalReference,
            ]);

            return PaymentPreferenceResponse::fromArray([
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
            ]);
        } catch (MPApiException $e) {
            Log::error('Mercado Pago API error creating preference', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'api_response' => $e->getApiResponse(),
            ]);

            throw new InvalidOperationException(
                'Failed to create payment preference: ' . $e->getMessage(),
                'MERCADOPAGO_PREFERENCE_FAILED'
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error creating Mercado Pago preference', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidOperationException(
                'Failed to create payment preference',
                'MERCADOPAGO_PREFERENCE_FAILED'
            );
        }
    }

    /**
     * Get payment information from Mercado Pago by payment ID.
     *
     * @param string $paymentId
     * @return array<string, mixed>|null
     */
    public function getPayment(string $paymentId): ?array
    {
        $this->ensureInitialized();
        try {
            Log::info('Fetching payment from Mercado Pago', [
                'payment_id' => $paymentId,
            ]);

            $payment = $this->paymentClient->get((int) $paymentId);

            Log::info('Payment fetched successfully', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
            ]);

            return [
                'id' => (string) $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'external_reference' => $payment->external_reference,
                'transaction_amount' => $payment->transaction_amount,
                'currency_id' => $payment->currency_id,
                'date_approved' => $payment->date_approved?->format('Y-m-d H:i:s'),
                'payer' => [
                    'email' => $payment->payer?->email,
                    'identification' => $payment->payer?->identification,
                ],
                'payment_method_id' => $payment->payment_method_id,
                'payment_type_id' => $payment->payment_type_id,
            ];
        } catch (MPApiException $e) {
            Log::error('Mercado Pago API error fetching payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching payment from Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate webhook request signature.
     * Mercado Pago includes x-signature and x-request-id headers for webhook validation.
     *
     * @param array<string, mixed> $headers
     * @param string $rawBody
     * @return bool
     */
    public function validateWebhookSignature(array $headers, string $rawBody): bool
    {
        // Fail closed when secret is missing to avoid accepting unsigned webhooks.
        if (empty($this->webhookSecret)) {
            Log::error('Webhook signature validation failed - no secret configured');
            return false;
        }

        // Get signature components from headers
        $xSignature = $headers['x-signature'] ?? $headers['X-Signature'] ?? null;
        $xRequestId = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? null;

        if (!$xSignature || !$xRequestId) {
            Log::warning('Missing webhook signature headers', [
                'has_x_signature' => !empty($xSignature),
                'has_x_request_id' => !empty($xRequestId),
            ]);
            return false;
        }

        // Parse signature header (format: "ts=timestamp,v1=hash")
        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        if (!isset($parts['ts'], $parts['v1'])) {
            Log::warning('Invalid signature format', ['signature' => $xSignature]);
            return false;
        }

        // Build the manifest string: id + request_id + raw_body
        $manifest = $parts['ts'] . $xRequestId . $rawBody;

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $manifest, $this->webhookSecret);

        // Compare signatures
        $isValid = hash_equals($expectedSignature, $parts['v1']);

        if (!$isValid) {
            Log::warning('Webhook signature validation failed', [
                'request_id' => $xRequestId,
            ]);
        }

        return $isValid;
    }

    /**
     * Map Mercado Pago payment status to application PaymentStatus enum.
     *
     * @param string $mpStatus
     * @return string
     */
    public function mapPaymentStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => 'paid',
            'pending', 'in_process' => 'pending',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }
}

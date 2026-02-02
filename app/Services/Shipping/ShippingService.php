<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ShippingStatus;
use App\Exceptions\Shipping\ShippingCreationException;
use App\Exceptions\Shipping\ShippingQuoteException;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\Shipping\Contracts\ShippingProviderInterface;
use App\Services\Shipping\DTOs\ShippingQuoteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Main shipping service orchestrator.
 * Single Responsibility: Coordinate shipping operations and persist results.
 * Open/Closed: Can work with any ShippingProviderInterface implementation.
 */
class ShippingService
{
    public function __construct(
        private readonly ShippingProviderInterface $provider,
    ) {
    }

    /**
     * Get shipping quote for given postal code and weight.
     *
     * @throws ShippingQuoteException
     */
    public function quote(string $postalCode, float $weight, float $declaredValue = 0): array
    {
        $originPostalCode = config('services.andreani.origin_postal_code')
            ?? Setting::get('shipping_origin_postal_code');

        if (!$originPostalCode) {
            Log::warning('Origin postal code not configured, using default quote');
            return $this->getDefaultQuote($weight);
        }

        try {
            $request = new ShippingQuoteRequest(
                originPostalCode: $originPostalCode,
                destinationPostalCode: $postalCode,
                weight: $weight,
                declaredValue: $declaredValue,
            );

            $response = $this->provider->getQuote($request);

            return $this->enrichQuoteWithFreeShipping($response->toArray());
        } catch (ShippingQuoteException $e) {
            Log::warning('Shipping quote failed, using default', [
                'exception' => $e->getMessage(),
                'postal_code' => $postalCode,
            ]);

            return $this->getDefaultQuote($weight);
        }
    }

    /**
     * Create shipment for order.
     *
     * @throws ShippingCreationException
     */
    public function createShipment(Order $order): Shipment
    {
        return DB::transaction(function () use ($order) {
            // Create pending shipment
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'provider' => $this->provider->getName(),
                'status' => ShippingStatus::PENDING,
            ]);

            try {
                // Create shipment with provider
                $response = $this->provider->createShipment($order);

                // Update shipment with provider response
                $shipment->update([
                    'tracking_number' => $response->trackingNumber,
                    'label_url' => $response->labelUrl,
                    'estimated_delivery' => $response->estimatedDelivery,
                    'metadata' => $response->metadata,
                    'status' => ShippingStatus::SHIPPED,
                    'shipped_at' => now(),
                ]);

                // Update order status
                $order->updateStatus(
                    OrderStatus::SHIPPED,
                    "Shipment created with tracking: {$response->trackingNumber}"
                );

                Log::info('Shipment created successfully', [
                    'order_id' => $order->id,
                    'tracking_number' => $response->trackingNumber,
                ]);

                return $shipment->fresh();
            } catch (ShippingCreationException $e) {
                // Mark shipment as failed
                $shipment->update([
                    'status' => ShippingStatus::FAILED,
                    'metadata' => ['error' => $e->getMessage()],
                ]);

                throw $e;
            }
        });
    }

    /**
     * Track shipment by tracking number.
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $response = $this->provider->trackShipment($trackingNumber);
            return $response->toArray();
        } catch (\Exception $e) {
            Log::error('Shipment tracking failed', [
                'tracking_number' => $trackingNumber,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process webhook notification from shipping provider.
     */
    public function processWebhook(array $data): void
    {
        $trackingNumber = $data['numeroAndreani'] ?? $data['tracking_number'] ?? null;

        if (!$trackingNumber) {
            Log::warning('Webhook received without tracking number', ['data' => $data]);
            return;
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (!$shipment) {
            Log::warning('Shipment not found for webhook', [
                'tracking_number' => $trackingNumber,
            ]);
            return;
        }

        $this->updateShipmentFromWebhook($shipment, $data);
    }

    /**
     * Update shipment status from webhook data.
     */
    private function updateShipmentFromWebhook(Shipment $shipment, array $data): void
    {
        $andreaniStatus = $data['estado'] ?? $data['status'] ?? '';

        if (empty($andreaniStatus)) {
            return;
        }

        $status = $this->provider instanceof AndreaniShippingProvider
            ? $this->provider->mapStatus($andreaniStatus)
            : $this->mapAndreaniStatus($andreaniStatus);

        $updateData = ['status' => $status];

        // Add metadata
        if (!empty($data)) {
            $updateData['metadata'] = array_merge(
                $shipment->metadata ?? [],
                ['last_webhook' => $data]
            );
        }

        $shipment->update($updateData);

        // Handle status-specific actions
        if ($status === ShippingStatus::DELIVERED && !$shipment->delivered_at) {
            $shipment->markAsDelivered();
            $shipment->order->updateStatus(
                OrderStatus::DELIVERED,
                'Order delivered by shipping provider'
            );
        }

        Log::info('Shipment updated from webhook', [
            'tracking_number' => $shipment->tracking_number,
            'status' => $status->value,
        ]);
    }

    /**
     * Get default shipping quote when provider is unavailable.
     */
    private function getDefaultQuote(float $weight): array
    {
        $freeThreshold = Setting::get('free_shipping_threshold', 50000);
        $baseCost = 2500;

        return [
            'provider' => 'standard',
            'options' => [
                [
                    'service_code' => 'standard',
                    'service_name' => 'Envío Estándar',
                    'cost' => $baseCost + ($weight * 100),
                    'estimated_days' => 5,
                    'description' => 'Envío estándar a domicilio',
                ],
            ],
            'free_threshold' => $freeThreshold,
        ];
    }

    /**
     * Add free shipping threshold to quote response.
     */
    private function enrichQuoteWithFreeShipping(array $quote): array
    {
        $freeThreshold = Setting::get('free_shipping_threshold', 50000);
        $quote['free_threshold'] = $freeThreshold;

        return $quote;
    }

    /**
     * Fallback status mapping.
     */
    private function mapAndreaniStatus(string $status): ShippingStatus
    {
        return match (strtolower($status)) {
            'en preparacion', 'ingresado' => ShippingStatus::PENDING,
            'en_camino', 'en camino', 'en transito' => ShippingStatus::IN_TRANSIT,
            'despachado', 'en distribucion' => ShippingStatus::SHIPPED,
            'entregado', 'delivered' => ShippingStatus::DELIVERED,
            'fallido', 'failed', 'no entregado' => ShippingStatus::FAILED,
            default => ShippingStatus::PENDING,
        };
    }
}


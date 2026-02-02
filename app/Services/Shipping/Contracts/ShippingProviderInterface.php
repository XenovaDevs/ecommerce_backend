<?php

declare(strict_types=1);

namespace App\Services\Shipping\Contracts;

use App\Models\Order;
use App\Services\Shipping\DTOs\ShippingQuoteRequest;
use App\Services\Shipping\DTOs\ShippingQuoteResponse;
use App\Services\Shipping\DTOs\ShipmentCreationResponse;
use App\Services\Shipping\DTOs\TrackingResponse;

/**
 * Contract for shipping provider implementations.
 * Ensures all providers implement required operations.
 */
interface ShippingProviderInterface
{
    /**
     * Get shipping quote for given parameters.
     *
     * @param ShippingQuoteRequest $request
     * @return ShippingQuoteResponse
     * @throws \App\Exceptions\Shipping\ShippingQuoteException
     */
    public function getQuote(ShippingQuoteRequest $request): ShippingQuoteResponse;

    /**
     * Create a shipment for an order.
     *
     * @param Order $order
     * @return ShipmentCreationResponse
     * @throws \App\Exceptions\Shipping\ShippingCreationException
     */
    public function createShipment(Order $order): ShipmentCreationResponse;

    /**
     * Track a shipment by tracking number.
     *
     * @param string $trackingNumber
     * @return TrackingResponse
     * @throws \App\Exceptions\Shipping\ShippingTrackingException
     */
    public function trackShipment(string $trackingNumber): TrackingResponse;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string;
}

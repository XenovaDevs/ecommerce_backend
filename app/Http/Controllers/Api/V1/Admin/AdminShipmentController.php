<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\Shipping\ShippingCreationException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\ShippingService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Admin Shipment Controller.
 * Single Responsibility: Handle admin-only shipment creation operations.
 *
 * @ai-context Admin controller for creating shipments in Andreani
 * @ai-dependencies ShippingService, Order model
 * @ai-flow Admin creates shipment -> Validates order -> Calls ShippingService -> Returns response
 */
class AdminShipmentController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ShippingService $shippingService
    ) {}

    /**
     * Create shipment for an order in Andreani.
     * Only accessible by administrators with proper permissions.
     *
     * @param Order $order Order model instance (route model binding)
     * @return JsonResponse
     */
    public function create(Order $order): JsonResponse
    {
        try {
            // Validate order can have shipment created
            $this->validateOrderForShipment($order);

            // Create shipment via ShippingService
            $shipment = $this->shippingService->createShipment($order);

            Log::info('Admin created shipment', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'admin_id' => auth()->id(),
            ]);

            return $this->success(
                [
                    'shipment' => [
                        'id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'label_url' => $shipment->label_url,
                        'status' => $shipment->status->value,
                        'estimated_delivery' => $shipment->estimated_delivery?->format('Y-m-d'),
                        'shipped_at' => $shipment->shipped_at?->toISOString(),
                    ],
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status->value,
                    ],
                ],
                'Shipment created successfully'
            );
        } catch (ShippingCreationException $e) {
            Log::warning('Shipment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'metadata' => $e->getMetadata(),
            ]);

            return $this->error(
                $e->getMessage(),
                $e->getErrorCode(),
                $e->getHttpStatus(),
                $e->getMetadata()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error creating shipment', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'An unexpected error occurred while creating shipment',
                'SHIPMENT_CREATION_ERROR',
                500
            );
        }
    }

    /**
     * Validate that order is eligible for shipment creation.
     *
     * @param Order $order
     * @throws \InvalidArgumentException
     */
    private function validateOrderForShipment(Order $order): void
    {
        // Check if order has shipping address
        if (!$order->shippingAddress) {
            throw new \InvalidArgumentException('Order must have a shipping address');
        }

        // Check if shipment already exists
        if ($order->shipment()->exists()) {
            throw new \InvalidArgumentException('Order already has a shipment');
        }

        // Check if order is paid (optional - depends on business rules)
        if ($order->payment_status->value !== 'paid') {
            throw new \InvalidArgumentException('Order must be paid before creating shipment');
        }

        // Check if order is cancelled
        if ($order->status->value === 'cancelled') {
            throw new \InvalidArgumentException('Cannot create shipment for cancelled order');
        }
    }
}

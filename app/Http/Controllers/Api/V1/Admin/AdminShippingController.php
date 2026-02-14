<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Shipment;
use App\Services\Shipping\ShippingService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @ai-context AdminShippingController handles shipping rates and shipment management for CMS.
 */
class AdminShippingController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = Shipment::query()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $shipments = collect($paginator->items())
            ->map(fn (Shipment $shipment) => $this->mapShipment($shipment))
            ->values();

        return $this->success([
            'data' => $shipments,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'carrier' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.street' => ['nullable', 'string'],
            'shipping_address.number' => ['nullable', 'string'],
            'shipping_address.floor' => ['nullable', 'string'],
            'shipping_address.apartment' => ['nullable', 'string'],
            'shipping_address.city' => ['nullable', 'string'],
            'shipping_address.state' => ['nullable', 'string'],
            'shipping_address.postal_code' => ['nullable', 'string'],
            'shipping_address.country' => ['nullable', 'string'],
        ]);

        $order = Order::with(['shippingAddress', 'shipment'])->findOrFail((int) $validated['order_id']);

        if ($order->shipment()->exists()) {
            return $this->error('Order already has a shipment', 'SHIPMENT_EXISTS', 422);
        }

        if (!$order->shippingAddress && !empty($validated['shipping_address'])) {
            $shippingAddress = $validated['shipping_address'];
            $address = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'name' => $order->user?->name ?? 'Customer',
                'phone' => $order->user?->phone,
                'address' => trim(($shippingAddress['street'] ?? '') . ' ' . ($shippingAddress['number'] ?? '')),
                'address_line_2' => trim(implode(' ', array_filter([
                    $shippingAddress['floor'] ?? null,
                    $shippingAddress['apartment'] ?? null,
                ]))),
                'city' => $shippingAddress['city'] ?? '',
                'state' => $shippingAddress['state'] ?? '',
                'postal_code' => $shippingAddress['postal_code'] ?? '',
                'country' => $shippingAddress['country'] ?? 'Argentina',
            ]);

            $order->shipping_address_id = $address->id;
            $order->save();
            $order->load('shippingAddress');
        }

        try {
            $shipment = $this->shippingService()->createShipment($order);
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage(), 'SHIPMENT_CREATE_FAILED', 422);
        }

        return $this->success($this->mapShipment($shipment->fresh()));
    }

    public function availableOrders(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 50), 200));

        $orders = Order::query()
            ->with(['user:id,name', 'shippingAddress'])
            ->whereDoesntHave('shipment')
            ->whereNotNull('shipping_address_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Order $order): array {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user?->name ?? 'Customer',
                    'shipping_address' => $this->mapAddress($order->shippingAddress),
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return $this->success($orders);
    }

    public function track(int $id): JsonResponse
    {
        $shipment = Shipment::with('order')->find($id);

        if (!$shipment) {
            return $this->success([
                'shipment' => null,
                'events' => [],
                'estimated_delivery' => null,
            ]);
        }

        $events = [];
        if (!empty($shipment->tracking_number)) {
            try {
                $tracking = $this->shippingService()->trackShipment($shipment->tracking_number);
                $events = collect($tracking['events'] ?? [])->map(function (array $event, int $index) use ($shipment, $tracking): array {
                    $status = $event['status'] ?? $tracking['status'] ?? 'in_transit';

                    return [
                        'id' => $index + 1,
                        'shipment_id' => $shipment->id,
                        'status' => Str::of((string) $status)->lower()->replace(' ', '_')->value(),
                        'description' => (string) ($event['description'] ?? $event['detail'] ?? $event['estado'] ?? 'Shipment update'),
                        'location' => $event['location'] ?? $event['branch'] ?? $event['sucursal'] ?? null,
                        'timestamp' => (string) ($event['timestamp'] ?? $event['date'] ?? $event['fecha'] ?? now()->toIso8601String()),
                    ];
                })->values()->all();
            } catch (\Throwable) {
                $events = [];
            }
        }

        return $this->success([
            'shipment' => $this->mapShipment($shipment),
            'events' => $events,
            'estimated_delivery' => $shipment->estimated_delivery?->toIso8601String(),
        ]);
    }

    public function rates(Request $request): JsonResponse
    {
        // Mock shipping rates for MVP
        // In production, integrate with real shipping API (Andreani, etc.)
        $rates = [
            [
                'id' => 1,
                'name' => 'Estándar',
                'carrier' => 'Andreani',
                'delivery_time' => '3-5 días hábiles',
                'base_cost' => 500.00,
                'free_shipping_threshold' => 5000.00,
            ],
            [
                'id' => 2,
                'name' => 'Express',
                'carrier' => 'Andreani',
                'delivery_time' => '24-48 horas',
                'base_cost' => 800.00,
                'free_shipping_threshold' => 10000.00,
            ],
            [
                'id' => 3,
                'name' => 'Retiro en sucursal',
                'carrier' => 'Andreani',
                'delivery_time' => '2-3 días hábiles',
                'base_cost' => 0.00,
                'free_shipping_threshold' => 0.00,
            ],
        ];

        return $this->success($rates);
    }

    private function mapShipment(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'tracking_number' => (string) ($shipment->tracking_number ?? ''),
            'carrier' => Str::headline((string) ($shipment->provider ?? 'andreani')),
            'status' => $shipment->status?->value ?? 'pending',
            'created_at' => $shipment->created_at?->toIso8601String(),
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    private function mapAddress(?OrderAddress $address): array
    {
        if (!$address) {
            return [
                'street' => '',
                'number' => '',
                'floor' => '',
                'apartment' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'Argentina',
            ];
        }

        return [
            'street' => (string) $address->address,
            'number' => '',
            'floor' => '',
            'apartment' => (string) ($address->address_line_2 ?? ''),
            'city' => (string) ($address->city ?? ''),
            'state' => (string) ($address->state ?? ''),
            'postal_code' => (string) ($address->postal_code ?? ''),
            'country' => (string) ($address->country ?? 'Argentina'),
        ];
    }

    private function shippingService(): ShippingService
    {
        return app(ShippingService::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment?->gateway ?? null,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'notes' => $this->notes,
            'item_count' => $this->item_count,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipping_address' => new OrderAddressResource($this->whenLoaded('shippingAddress')),
            'billing_address' => new OrderAddressResource($this->whenLoaded('billingAddress')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

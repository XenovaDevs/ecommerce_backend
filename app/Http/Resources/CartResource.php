<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->item_count,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) ($this->discount ?? 0),
            'tax' => (float) ($this->tax ?? 0),
            'total' => (float) $this->total,
            'is_empty' => $this->is_empty,
            'coupons' => $this->whenLoaded('coupons', function () {
                return $this->coupons->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'type' => $coupon->type,
                        'value' => (float) $coupon->value,
                        'discount_amount' => (float) $coupon->calculateDiscount(
                            $this->subtotal - $this->discount
                        ),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

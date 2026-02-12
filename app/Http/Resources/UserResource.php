<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'orders_count' => $this->when(
                array_key_exists('orders_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->orders_count
            ),
            'total_spent' => $this->when(
                array_key_exists('total_spent', $this->resource->getAttributes()),
                fn () => (float) ($this->resource->total_spent ?? 0)
            ),
        ];
    }
}

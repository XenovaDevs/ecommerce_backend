<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'amount' => $this->amount,
            'currency' => $this->currency,
            'external_id' => $this->external_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

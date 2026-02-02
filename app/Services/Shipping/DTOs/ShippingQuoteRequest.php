<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for shipping quote requests.
 * Immutable value object.
 */
final class ShippingQuoteRequest
{
    public function __construct(
        public readonly string $originPostalCode,
        public readonly string $destinationPostalCode,
        public readonly float $weight,
        public readonly float $declaredValue,
        public readonly ?int $volumeInCm3 = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            originPostalCode: $data['origin_postal_code'],
            destinationPostalCode: $data['destination_postal_code'],
            weight: (float) $data['weight'],
            declaredValue: (float) $data['declared_value'],
            volumeInCm3: isset($data['volume']) ? (int) $data['volume'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'origin_postal_code' => $this->originPostalCode,
            'destination_postal_code' => $this->destinationPostalCode,
            'weight' => $this->weight,
            'declared_value' => $this->declaredValue,
            'volume' => $this->volumeInCm3,
        ];
    }
}

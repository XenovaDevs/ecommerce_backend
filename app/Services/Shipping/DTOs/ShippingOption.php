<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for individual shipping option.
 * Immutable value object.
 */
final class ShippingOption
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly string $serviceName,
        public readonly float $cost,
        public readonly int $estimatedDays,
        public readonly ?string $description = null,
    ) {
    }

    public static function fromAndreaniResponse(array $data): self
    {
        return new self(
            serviceCode: $data['productoAEntregar'] ?? 'standard',
            serviceName: $data['producto'] ?? 'Envío Estándar',
            cost: (float) ($data['tarifaSinIva'] ?? 0),
            estimatedDays: (int) ($data['plazoEntrega'] ?? 5),
            description: $data['descripcion'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'service_code' => $this->serviceCode,
            'service_name' => $this->serviceName,
            'cost' => $this->cost,
            'estimated_days' => $this->estimatedDays,
            'description' => $this->description,
        ];
    }
}

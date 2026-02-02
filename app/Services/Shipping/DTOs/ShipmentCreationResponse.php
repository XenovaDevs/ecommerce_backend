<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for shipment creation responses.
 * Immutable value object.
 */
final class ShipmentCreationResponse
{
    public function __construct(
        public readonly string $trackingNumber,
        public readonly ?string $labelUrl,
        public readonly ?\DateTimeImmutable $estimatedDelivery,
        public readonly array $metadata = [],
    ) {
    }

    public static function fromAndreaniResponse(array $data): self
    {
        $estimatedDelivery = null;
        if (isset($data['fechaEntregaEstimada'])) {
            $estimatedDelivery = \DateTimeImmutable::createFromFormat(
                'Y-m-d',
                $data['fechaEntregaEstimada']
            ) ?: null;
        }

        return new self(
            trackingNumber: $data['numeroAndreani'] ?? '',
            labelUrl: $data['urlEtiqueta'] ?? null,
            estimatedDelivery: $estimatedDelivery,
            metadata: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'label_url' => $this->labelUrl,
            'estimated_delivery' => $this->estimatedDelivery?->format('Y-m-d'),
            'metadata' => $this->metadata,
        ];
    }
}

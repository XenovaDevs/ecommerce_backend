<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for individual tracking event.
 * Immutable value object.
 */
final class TrackingEvent
{
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $status,
        public readonly string $description,
        public readonly ?string $location = null,
    ) {
    }

    public static function fromAndreaniResponse(array $data): self
    {
        $timestamp = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s',
            $data['fecha'] ?? ''
        );

        if (!$timestamp) {
            $timestamp = new \DateTimeImmutable();
        }

        return new self(
            timestamp: $timestamp,
            status: $data['estado'] ?? '',
            description: $data['motivo'] ?? '',
            location: $data['sucursal'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'description' => $this->description,
            'location' => $this->location,
        ];
    }
}

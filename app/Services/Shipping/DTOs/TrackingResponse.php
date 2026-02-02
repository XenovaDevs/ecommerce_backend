<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for tracking responses.
 * Immutable value object.
 */
final class TrackingResponse
{
    /**
     * @param TrackingEvent[] $events
     */
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $status,
        public readonly array $events,
        public readonly ?\DateTimeImmutable $lastUpdate,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status,
            'events' => array_map(
                fn(TrackingEvent $event) => $event->toArray(),
                $this->events
            ),
            'last_update' => $this->lastUpdate?->format('Y-m-d H:i:s'),
        ];
    }
}

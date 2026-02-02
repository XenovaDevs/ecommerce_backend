<?php

declare(strict_types=1);

namespace App\Services\Shipping\DTOs;

/**
 * Data Transfer Object for shipping quote responses.
 * Immutable value object.
 */
final class ShippingQuoteResponse
{
    /**
     * @param ShippingOption[] $options
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $options,
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'options' => array_map(
                fn(ShippingOption $option) => $option->toArray(),
                $this->options
            ),
        ];
    }
}

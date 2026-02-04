<?php

declare(strict_types=1);

namespace App\Services\Payment\DTOs;

/**
 * @ai-context PaymentPreferenceResponse encapsulates the response from payment
 *             gateway after creating a preference. Following DTO pattern, this
 *             class is immutable and provides type-safe access to response data.
 */
final readonly class PaymentPreferenceResponse
{
    public function __construct(
        public string $preferenceId,
        public string $initPoint,
        public ?string $sandboxInitPoint = null
    ) {}

    /**
     * Create from Mercado Pago API response array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            preferenceId: $data['id'] ?? throw new \InvalidArgumentException('Missing preference ID'),
            initPoint: $data['init_point'] ?? throw new \InvalidArgumentException('Missing init point'),
            sandboxInitPoint: $data['sandbox_init_point'] ?? null
        );
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'preference_id' => $this->preferenceId,
            'init_point' => $this->initPoint,
            'sandbox_init_point' => $this->sandboxInitPoint,
        ], fn ($value) => $value !== null);
    }

    /**
     * Get the appropriate init point based on environment.
     *
     * @return string
     */
    public function getInitPoint(): string
    {
        // Use sandbox URL in development/testing environments
        if (app()->environment('local', 'testing') && $this->sandboxInitPoint !== null) {
            return $this->sandboxInitPoint;
        }

        return $this->initPoint;
    }
}

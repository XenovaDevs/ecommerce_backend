<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/**
 * @ai-context CreateOrderDTO for order creation data transfer.
 */
readonly class CreateOrderDTO
{
    public function __construct(
        public int $user_id,
        public array $shipping_address,
        public array $billing_address,
        public ?string $notes = null,
        public ?string $payment_method = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
        ];
    }
}

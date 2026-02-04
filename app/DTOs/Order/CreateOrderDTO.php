<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use Illuminate\Http\Request;

/**
 * @ai-context CreateOrderDTO for order creation data transfer.
 */
readonly class CreateOrderDTO
{
    public function __construct(
        public array $shippingAddress,
        public ?array $billingAddress = null,
        public float $shippingCost = 0.0,
        public ?string $notes = null,
        public ?string $paymentMethod = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            shippingAddress: $request->input('shipping_address'),
            billingAddress: $request->input('billing_address'),
            shippingCost: (float) $request->input('shipping_cost', 0),
            notes: $request->input('notes'),
            paymentMethod: $request->input('payment_method', 'mercadopago'),
        );
    }

    public function toArray(): array
    {
        return [
            'shipping_address' => $this->shippingAddress,
            'billing_address' => $this->billingAddress,
            'shipping_cost' => $this->shippingCost,
            'notes' => $this->notes,
            'payment_method' => $this->paymentMethod,
        ];
    }
}

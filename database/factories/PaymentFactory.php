<?php

namespace Database\Factories;

use App\Domain\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => fake()->randomFloat(2, 50, 1000),
            'currency' => 'ARS',
            'external_id' => fake()->optional()->uuid(),
            'metadata' => [],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PAID,
            'external_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
        ]);
    }

    public function mercadopago(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'mercado_pago',
        ]);
    }
}

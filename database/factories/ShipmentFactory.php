<?php

namespace Database\Factories;

use App\Domain\Enums\ShippingStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => fake()->randomElement(['andreani', 'oca', 'correo_argentino']),
            'tracking_number' => fake()->optional()->numerify('########'),
            'status' => ShippingStatus::PENDING,
            'label_url' => fake()->optional()->url(),
            'estimated_delivery' => fake()->optional()->dateTimeBetween('+3 days', '+10 days'),
            'shipped_at' => null,
            'delivered_at' => null,
            'metadata' => [],
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingStatus::SHIPPED,
            'tracking_number' => fake()->numerify('########'),
            'shipped_at' => now(),
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingStatus::IN_TRANSIT,
            'tracking_number' => fake()->numerify('########'),
            'shipped_at' => now()->subDays(2),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingStatus::DELIVERED,
            'tracking_number' => fake()->numerify('########'),
            'shipped_at' => now()->subDays(5),
            'delivered_at' => now(),
        ]);
    }
}

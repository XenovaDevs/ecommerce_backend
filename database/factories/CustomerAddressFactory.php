<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', 'Other']),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'Argentina',
            'type' => fake()->randomElement(['shipping', 'billing']),
            'is_default' => false,
        ];
    }

    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'shipping',
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}

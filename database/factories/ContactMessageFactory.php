<?php

namespace Database\Factories;

use App\Domain\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'message' => fake()->paragraphs(3, true),
            'status' => ContactMessageStatus::PENDING,
            'reply' => null,
            'admin_reply' => null,
        ];
    }

    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactMessageStatus::REPLIED,
            'reply' => fake()->paragraphs(2, true),
            'admin_reply' => fake()->paragraphs(2, true),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactMessageStatus::CLOSED,
        ]);
    }
}

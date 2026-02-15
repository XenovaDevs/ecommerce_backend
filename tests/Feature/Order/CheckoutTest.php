<?php

namespace Tests\Feature\Order;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_checkout_with_valid_cart(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', [
                'shipping_address' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+1234567890',
                    'address' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA',
                ],
                'billing_address' => [
                    'name' => 'John Doe',
                    'phone' => '+1234567890',
                    'address' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA',
                ],
                'shipping_cost' => 0,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['order'],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_checkout_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', [
                'shipping_address' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+1234567890',
                    'address' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA',
                ],
                'billing_address' => [
                    'name' => 'John Doe',
                    'phone' => '+1234567890',
                    'address' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA',
                ],
                'shipping_cost' => 0,
            ]);

        $response->assertStatus(409);
    }
}

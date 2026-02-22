<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;
use Tests\Traits\AssertValidationErrors;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase, AuthHelpers, AssertValidationErrors;

    public function test_customer_can_view_their_orders(): void
    {
        $user = $this->actingAsCustomer();

        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->count(2)->create(); // Other user's orders

        $response = $this->getJson('/api/v1/customer/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_customer_can_view_single_order(): void
    {
        $user = $this->actingAsCustomer();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/customer/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_customer_cannot_view_other_customer_order(): void
    {
        $this->actingAsCustomer();
        $otherUser = $this->createUser(\App\Domain\Enums\UserRole::CUSTOMER);
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/v1/customer/orders/{$otherOrder->id}");

        $response->assertStatus(404);
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        $user = $this->actingAsCustomer();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/customer/orders/{$order->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_customer_cannot_cancel_completed_order(): void
    {
        $user = $this->actingAsCustomer();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'delivered',
        ]);

        $response = $this->postJson("/api/v1/customer/orders/{$order->id}/cancel");

        $response->assertStatus(409);
    }

    public function test_customer_can_checkout_with_cart_items(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'type' => 'shipping',
        ]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        // Add item to cart
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Checkout
        $response = $this->postJson('/api/v1/checkout', [
            'shipping_address' => [
                'name' => $address->name,
                'email' => $user->email,
                'phone' => $address->phone,
                'address' => $address->address,
                'city' => $address->city,
                'state' => $address->state ?? 'BA',
                'postal_code' => $address->postal_code,
                'country' => $address->country ?? 'AR',
            ],
            'shipping_cost' => 0,
            'payment_method' => 'mercadopago',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order' => ['id'],
                    'payment_url',
                ],
            ]);
    }

    public function test_checkout_requires_shipping_address(): void
    {
        $this->actingAsCustomer();
        $product = Product::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'payment_method' => 'mercadopago',
        ]);

        $this->assertCustomValidationErrors($response, ['shipping_address']);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/v1/checkout', [
            'shipping_address' => [
                'name' => $address->name,
                'email' => $user->email,
                'phone' => $address->phone,
                'address' => $address->address,
                'city' => $address->city,
                'state' => $address->state ?? 'BA',
                'postal_code' => $address->postal_code,
                'country' => $address->country ?? 'AR',
            ],
            'shipping_cost' => 0,
            'payment_method' => 'mercadopago',
        ]);

        $response->assertStatus(409);
    }

    public function test_checkout_validates_stock_availability(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $product->update(['stock' => 0]);

        $response = $this->postJson('/api/v1/checkout', [
            'shipping_address' => [
                'name' => $address->name,
                'email' => $user->email,
                'phone' => $address->phone,
                'address' => $address->address,
                'city' => $address->city,
                'state' => $address->state ?? 'BA',
                'postal_code' => $address->postal_code,
                'country' => $address->country ?? 'AR',
            ],
            'shipping_cost' => 0,
            'payment_method' => 'mercadopago',
        ]);

        $response->assertStatus(409);
    }
}

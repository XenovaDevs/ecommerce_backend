<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class CartManagementTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    private string $sessionId = 'cart-test-session';

    protected function setUp(): void
    {
        parent::setUp();
        Product::factory()->count(3)->create();
    }

    public function test_guest_can_view_cart(): void
    {
        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->getJson('/api/v1/cart');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['items', 'total'],
            ]);
    }

    public function test_guest_can_add_items_to_cart(): void
    {
        $product = Product::first();

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();
    }

    public function test_authenticated_user_can_add_items_to_cart(): void
    {
        $this->actingAsCustomer();
        $product = Product::first();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
    }

    public function test_can_update_cart_item_quantity(): void
    {
        $product = Product::first();

        // Add item
        $this->withHeader('X-Session-ID', $this->sessionId)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $itemId = $this->withHeader('X-Session-ID', $this->sessionId)
            ->getJson('/api/v1/cart')
            ->json('data.items.0.id');

        // Update quantity
        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->putJson("/api/v1/cart/items/{$itemId}", [
            'quantity' => 3,
        ]);

        $response->assertOk();
    }

    public function test_can_remove_item_from_cart(): void
    {
        $product = Product::first();

        // Add item
        $this->withHeader('X-Session-ID', $this->sessionId)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $itemId = $this->withHeader('X-Session-ID', $this->sessionId)
            ->getJson('/api/v1/cart')
            ->json('data.items.0.id');

        // Remove item
        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->deleteJson("/api/v1/cart/items/{$itemId}");

        $response->assertNoContent();
    }

    public function test_can_clear_entire_cart(): void
    {
        $product = Product::first();

        // Add items
        $this->withHeader('X-Session-ID', $this->sessionId)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Clear cart
        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->deleteJson('/api/v1/cart');

        $response->assertOk();

        // Verify cart is empty
        $cartResponse = $this->withHeader('X-Session-ID', $this->sessionId)
            ->getJson('/api/v1/cart');
        $this->assertEmpty($cartResponse->json('data.items'));
    }

    public function test_cannot_add_invalid_product_to_cart(): void
    {
        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => 99999,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_add_zero_quantity_to_cart(): void
    {
        $product = Product::first();

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_add_negative_quantity_to_cart(): void
    {
        $product = Product::first();

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => -1,
        ]);

        $response->assertStatus(422);
    }

    public function test_cart_calculates_total_correctly(): void
    {
        $product1 = Product::factory()->create(['price' => 100, 'is_active' => true]);
        $product2 = Product::factory()->create(['price' => 50, 'is_active' => true]);

        $this->withHeader('X-Session-ID', $this->sessionId)->postJson('/api/v1/cart/items', [
            'product_id' => $product1->id,
            'quantity' => 2,
        ]);

        $this->withHeader('X-Session-ID', $this->sessionId)->postJson('/api/v1/cart/items', [
            'product_id' => $product2->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->getJson('/api/v1/cart');

        $total = $response->json('data.total');
        $this->assertEquals(250, $total);
    }

    public function test_cannot_add_out_of_stock_product_to_cart(): void
    {
        $product = Product::factory()->create([
            'stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_add_quantity_exceeding_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->withHeader('X-Session-ID', $this->sessionId)
            ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
    }
}

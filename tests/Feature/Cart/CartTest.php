<?php

namespace Tests\Feature\Cart;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_add_product_to_cart(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['items', 'total'],
            ]);
    }

    public function test_cannot_add_out_of_stock_product(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_get_current_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cart');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['items', 'subtotal', 'total'],
            ]);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $cart = $this->user->cart;
        $item = $cart->items()->first();

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/cart/items/' . $item->id);

        $response->assertStatus(204);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Wishlist;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class WishlistManagementTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_customer_can_view_wishlist(): void
    {
        $user = $this->actingAsCustomer();
        $products = Product::factory()->count(3)->create();

        foreach ($products as $product) {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
        }

        $response = $this->getJson('/api/v1/wishlist');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_customer_can_add_product_to_wishlist(): void
    {
        $this->actingAsCustomer();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/wishlist', [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('wishlists', [
            'product_id' => $product->id,
        ]);
    }

    public function test_customer_can_remove_product_from_wishlist(): void
    {
        $user = $this->actingAsCustomer();
        $product = Product::factory()->create();

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson("/api/v1/wishlist/{$product->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_cannot_add_same_product_twice_to_wishlist(): void
    {
        $user = $this->actingAsCustomer();
        $product = Product::factory()->create();

        // Add first time
        $this->postJson('/api/v1/wishlist', [
            'product_id' => $product->id,
        ]);

        // Try to add again
        $response = $this->postJson('/api/v1/wishlist', [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_wishlist_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/wishlist');

        $response->assertStatus(401);
    }

    public function test_cannot_add_invalid_product_to_wishlist(): void
    {
        $this->actingAsCustomer();

        $response = $this->postJson('/api/v1/wishlist', [
            'product_id' => 99999,
        ]);

        $response->assertStatus(422);
    }

    public function test_wishlist_only_shows_own_items(): void
    {
        $user = $this->actingAsCustomer();
        $otherUser = $this->createUser(\App\Domain\Enums\UserRole::CUSTOMER);

        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        Wishlist::create(['user_id' => $user->id, 'product_id' => $product1->id]);
        Wishlist::create(['user_id' => $otherUser->id, 'product_id' => $product2->id]);

        $response = $this->getJson('/api/v1/wishlist');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_wishlist_returns_product_details(): void
    {
        $user = $this->actingAsCustomer();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'price' => 99.99,
        ]);

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson('/api/v1/wishlist');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test Product']);
    }
}

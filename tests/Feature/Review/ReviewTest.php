<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Domain\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_review_for_purchased_product(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Excellent product!',
                'comment' => 'Very satisfied with this purchase.',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'rating', 'title', 'comment'],
            ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'is_verified_purchase' => true,
        ]);
    }

    public function test_customer_cannot_review_product_without_purchasing(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Great!',
                'comment' => 'Awesome product.',
            ]);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_create_duplicate_review(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        Review::factory()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/reviews', [
                'product_id' => $product->id,
                'rating' => 4,
                'title' => 'Another review',
                'comment' => 'Trying to review again.',
            ]);

        $response->assertStatus(422);
    }

    public function test_customer_can_update_their_own_review(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $review = Review::factory()->create(['user_id' => $customer->id]);

        $response = $this->actingAs($customer)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 4,
                'title' => 'Updated title',
                'comment' => 'Updated comment.',
            ]);

        $response->assertStatus(200);

        $review->refresh();
        $this->assertEquals(4, $review->rating);
        $this->assertEquals('Updated title', $review->title);
        $this->assertEquals('Updated comment.', $review->comment);
    }

    public function test_customer_cannot_update_another_users_review(): void
    {
        $customer1 = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $customer2 = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $review = Review::factory()->create(['user_id' => $customer1->id]);

        $response = $this->actingAs($customer2)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 1,
                'title' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    public function test_customer_can_delete_their_own_review(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $review = Review::factory()->create(['user_id' => $customer->id]);

        $response = $this->actingAs($customer)
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_customer_can_mark_review_as_helpful(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $review = Review::factory()->create(['helpful_count' => 0]);

        $response = $this->actingAs($customer)
            ->postJson("/api/v1/reviews/{$review->id}/helpful");

        $response->assertStatus(200);

        $review->refresh();
        $this->assertEquals(1, $review->helpful_count);
    }

    public function test_guest_can_view_approved_reviews(): void
    {
        $product = Product::factory()->create();
        Review::factory()->count(3)->create([
            'product_id' => $product->id,
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/v1/reviews?product_id={$product->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_approve_review(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $review = Review::factory()->create(['is_approved' => false]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/reviews/{$review->id}/approve");

        $response->assertStatus(200);

        $review->refresh();
        $this->assertTrue($review->is_approved);
    }

    public function test_admin_can_reject_review(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $review = Review::factory()->create(['is_approved' => true]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/reviews/{$review->id}/reject");

        $response->assertStatus(200);

        $review->refresh();
        $this->assertFalse($review->is_approved);
    }
}

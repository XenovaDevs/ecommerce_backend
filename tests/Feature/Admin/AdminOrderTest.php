<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_list_all_orders(): void
    {
        $this->actingAsAdmin();

        Order::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/admin/orders');

        $response->assertOk()
            ->assertJsonCount(10, 'data');
    }

    public function test_admin_can_view_specific_order(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $response = $this->getJson("/api/v1/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_admin_can_update_order_status(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);
    }

    public function test_manager_can_update_order_status(): void
    {
        $this->actingAsManager();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_support_cannot_update_order_status(): void
    {
        $this->actingAsSupport();
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(403);
    }

    public function test_support_can_view_orders(): void
    {
        $this->actingAsSupport();
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/orders');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_can_filter_orders_by_status(): void
    {
        $this->actingAsAdmin();

        Order::factory()->count(3)->create(['status' => \App\Domain\Enums\OrderStatus::PENDING]);
        Order::factory()->count(2)->create(['status' => \App\Domain\Enums\OrderStatus::DELIVERED]);

        $response = $this->getJson('/api/v1/admin/orders?status=pending');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_orders_by_customer(): void
    {
        $this->actingAsAdmin();

        $customer = User::factory()->create(['role' => \App\Domain\Enums\UserRole::CUSTOMER]);
        Order::factory()->count(5)->create(['user_id' => $customer->id]);
        Order::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/admin/orders?user_id={$customer->id}");

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_order_status_update_validates_valid_statuses(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $response = $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['status']]]);
    }

    public function test_order_status_history_is_recorded(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create(['status' => \App\Domain\Enums\OrderStatus::PENDING]);

        $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'Processing',
        ]);
    }

    public function test_can_search_orders_by_order_number(): void
    {
        $this->actingAsAdmin();

        $order = Order::factory()->create(['order_number' => 'ORD-12345']);
        Order::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/orders?search=ORD-12345');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_orders_are_paginated(): void
    {
        $this->actingAsAdmin();
        Order::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/admin/orders?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'pagination' => ['current_page', 'total', 'per_page'],
            ]);
    }
}

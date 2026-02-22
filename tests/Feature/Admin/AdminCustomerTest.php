<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_list_customers(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(10)->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson('/api/v1/admin/customers');

        $response->assertOk()
            ->assertJsonCount(10, 'data');
    }

    public function test_admin_can_view_specific_customer(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson("/api/v1/admin/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonFragment(['email' => $customer->email]);
    }

    public function test_manager_can_view_customers(): void
    {
        $this->actingAsManager();
        User::factory()->count(5)->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson('/api/v1/admin/customers');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_support_can_view_customers(): void
    {
        $this->actingAsSupport();
        User::factory()->count(3)->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson('/api/v1/admin/customers');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_customer_list_does_not_include_admin_users(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(5)->create(['role' => UserRole::CUSTOMER]);
        User::factory()->count(3)->create(['role' => UserRole::ADMIN]);

        $response = $this->getJson('/api/v1/admin/customers');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_search_customers_by_name(): void
    {
        $this->actingAsAdmin();

        User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'name' => 'John Doe',
        ]);
        User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'name' => 'Jane Smith',
        ]);

        $response = $this->getJson('/api/v1/admin/customers?search=John');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_search_customers_by_email(): void
    {
        $this->actingAsAdmin();

        User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/v1/admin/customers?search=john@example');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_customer_detail_includes_order_count(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Order::factory()->count(5)->create(['user_id' => $customer->id]);

        $response = $this->getJson("/api/v1/admin/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.orders_count', 5);
    }

    public function test_customer_detail_includes_total_spent(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'total' => 100,
            'status' => \App\Domain\Enums\OrderStatus::DELIVERED,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'total' => 200,
            'status' => \App\Domain\Enums\OrderStatus::DELIVERED,
        ]);

        $response = $this->getJson("/api/v1/admin/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.total_spent', 300);
    }

    public function test_customers_are_paginated(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(30)->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson('/api/v1/admin/customers?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'pagination' => ['current_page', 'total', 'per_page'],
            ]);
    }
}

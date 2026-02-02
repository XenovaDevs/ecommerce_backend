<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'total_revenue',
                    'total_customers',
                    'total_products',
                ],
            ]);
    }

    public function test_manager_can_access_dashboard(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_support_can_access_dashboard(): void
    {
        $this->actingAsSupport();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_customer_cannot_access_dashboard(): void
    {
        $this->actingAsCustomer();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_shows_correct_statistics(): void
    {
        $this->actingAsAdmin();

        // Create test data
        $customers = User::factory()->count(5)->create(['role' => \App\Domain\Enums\UserRole::CUSTOMER]);
        Product::factory()->count(10)->create();
        // Use existing customers for orders to avoid creating additional users
        Order::factory()->count(3)->create([
            'total' => 100,
            'user_id' => $customers->first()->id,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(5, $data['total_customers']);
        $this->assertEquals(10, $data['total_products']);
        $this->assertEquals(3, $data['total_orders']);
        $this->assertEquals(300, $data['total_revenue']);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(401);
    }
}

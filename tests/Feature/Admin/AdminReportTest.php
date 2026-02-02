<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminReportTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_view_sales_report(): void
    {
        $this->actingAsAdmin();

        Order::factory()->count(5)->create(['total' => 100, 'status' => \App\Domain\Enums\OrderStatus::DELIVERED]);

        $response = $this->getJson('/api/v1/admin/reports/sales');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_sales',
                    'total_orders',
                    'average_order_value',
                ],
            ]);
    }

    public function test_admin_can_view_products_report(): void
    {
        $this->actingAsAdmin();

        Product::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/admin/reports/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_products',
                    'active_products',
                    'out_of_stock',
                ],
            ]);
    }

    public function test_admin_can_view_customers_report(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(20)->create(['role' => UserRole::CUSTOMER]);

        $response = $this->getJson('/api/v1/admin/reports/customers');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_customers',
                    'new_customers',
                ],
            ]);
    }

    public function test_manager_can_view_sales_and_products_reports(): void
    {
        $this->actingAsManager();

        $salesResponse = $this->getJson('/api/v1/admin/reports/sales');
        $productsResponse = $this->getJson('/api/v1/admin/reports/products');

        $this->assertNotEquals(403, $salesResponse->status());
        $this->assertNotEquals(403, $productsResponse->status());
    }

    public function test_manager_cannot_view_customers_report(): void
    {
        $this->actingAsManager();

        $response = $this->getJson('/api/v1/admin/reports/customers');

        $response->assertStatus(403);
    }

    public function test_support_cannot_view_reports(): void
    {
        $this->actingAsSupport();

        $salesResponse = $this->getJson('/api/v1/admin/reports/sales');
        $productsResponse = $this->getJson('/api/v1/admin/reports/products');
        $customersResponse = $this->getJson('/api/v1/admin/reports/customers');

        $salesResponse->assertStatus(403);
        $productsResponse->assertStatus(403);
        $customersResponse->assertStatus(403);
    }

    public function test_sales_report_can_be_filtered_by_date_range(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'total' => 100,
            'status' => \App\Domain\Enums\OrderStatus::DELIVERED,
            'created_at' => now()->subDays(5),
        ]);

        Order::factory()->create([
            'total' => 200,
            'status' => \App\Domain\Enums\OrderStatus::DELIVERED,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/v1/admin/reports/sales?from=' . now()->subDays(7)->toDateString());

        $response->assertOk();
    }

    public function test_sales_report_only_includes_completed_orders(): void
    {
        $this->actingAsAdmin();

        Order::factory()->count(5)->create(['total' => 100, 'status' => \App\Domain\Enums\OrderStatus::DELIVERED]);
        Order::factory()->count(3)->create(['total' => 100, 'status' => \App\Domain\Enums\OrderStatus::PENDING]);
        Order::factory()->count(2)->create(['total' => 100, 'status' => \App\Domain\Enums\OrderStatus::CANCELLED]);

        $response = $this->getJson('/api/v1/admin/reports/sales');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(5, $data['total_orders']);
        $this->assertEquals(500, $data['total_sales']);
    }

    public function test_products_report_shows_low_stock_products(): void
    {
        $this->actingAsAdmin();

        Product::factory()->count(3)->create(['stock' => 5]);
        Product::factory()->count(7)->create(['stock' => 50]);

        $response = $this->getJson('/api/v1/admin/reports/products');

        $response->assertOk()
            ->assertJsonPath('data.low_stock', 3);
    }

    public function test_customers_report_filters_new_customers_by_date(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(5)->create([
            'role' => UserRole::CUSTOMER,
            'created_at' => now()->subDays(5),
        ]);

        User::factory()->count(10)->create([
            'role' => UserRole::CUSTOMER,
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->getJson('/api/v1/admin/reports/customers?period=30');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(5, $data['new_customers']);
    }
}

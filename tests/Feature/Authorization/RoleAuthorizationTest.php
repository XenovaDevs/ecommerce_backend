<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some test data
        Category::factory()->create();
        Product::factory()->create();
    }

    public function test_super_admin_can_access_all_admin_endpoints(): void
    {
        $this->actingAsSuperAdmin();

        $endpoints = [
            ['GET', '/api/v1/admin/dashboard'],
            ['GET', '/api/v1/admin/categories'],
            ['GET', '/api/v1/admin/products'],
            ['GET', '/api/v1/admin/orders'],
            ['GET', '/api/v1/admin/customers'],
            ['GET', '/api/v1/admin/settings'],
            ['GET', '/api/v1/admin/reports/sales'],
            ['GET', '/api/v1/admin/reports/products'],
            ['GET', '/api/v1/admin/reports/customers'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $this->assertNotEquals(403, $response->status(), "Super admin should access {$url}");
        }
    }

    public function test_admin_can_access_permitted_endpoints(): void
    {
        $this->actingAsAdmin();

        $allowedEndpoints = [
            ['GET', '/api/v1/admin/dashboard'],
            ['GET', '/api/v1/admin/categories'],
            ['GET', '/api/v1/admin/products'],
            ['GET', '/api/v1/admin/orders'],
            ['GET', '/api/v1/admin/customers'],
            ['GET', '/api/v1/admin/settings'],
            ['GET', '/api/v1/admin/reports/sales'],
        ];

        foreach ($allowedEndpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $this->assertNotEquals(403, $response->status(), "Admin should access {$url}");
        }
    }

    public function test_manager_cannot_delete_products(): void
    {
        $this->actingAsManager();
        $product = Product::first();

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_manager_can_update_products(): void
    {
        $this->actingAsManager();
        $product = Product::first();

        $response = $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Updated Name',
            'price' => 99.99,
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_support_cannot_update_orders(): void
    {
        $this->actingAsSupport();
        $order = Order::factory()->create();

        $response = $this->putJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(403);
    }

    public function test_support_can_view_orders(): void
    {
        $this->actingAsSupport();

        $response = $this->getJson('/api/v1/admin/orders');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $this->actingAsCustomer();

        $adminEndpoints = [
            ['GET', '/api/v1/admin/dashboard'],
            ['GET', '/api/v1/admin/products'],
            ['GET', '/api/v1/admin/orders'],
            ['GET', '/api/v1/admin/customers'],
        ];

        foreach ($adminEndpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(403, "Customer should not access {$url}");
        }
    }

    public function test_customer_can_access_customer_endpoints(): void
    {
        $this->actingAsCustomer();

        $response = $this->getJson('/api/v1/customer/profile');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_unauthenticated_user_cannot_access_protected_endpoints(): void
    {
        $protectedEndpoints = [
            ['GET', '/api/v1/auth/me'],
            ['POST', '/api/v1/auth/logout'],
            ['GET', '/api/v1/customer/profile'],
            ['GET', '/api/v1/admin/dashboard'],
        ];

        foreach ($protectedEndpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401, "Unauthenticated user should get 401 for {$url}");
        }
    }

    public function test_admin_roles_hierarchy(): void
    {
        $product = Product::first();

        // Super Admin - can delete
        $this->actingAsSuperAdmin();
        $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$product->id}");
        $this->assertNotEquals(403, $deleteResponse->status());

        // Create new product for next test
        $product = Product::factory()->create();

        // Admin - can delete
        $this->actingAsAdmin();
        $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$product->id}");
        $this->assertNotEquals(403, $deleteResponse->status());

        // Create new product for next test
        $product = Product::factory()->create();

        // Manager - cannot delete
        $this->actingAsManager();
        $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$product->id}");
        $deleteResponse->assertStatus(403);

        // Support - cannot delete
        $this->actingAsSupport();
        $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$product->id}");
        $deleteResponse->assertStatus(403);
    }
}

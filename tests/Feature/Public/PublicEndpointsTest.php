<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertValidationErrors;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase, AssertValidationErrors;

    public function test_can_list_categories_without_authentication(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description'],
                ],
            ]);
    }

    public function test_can_view_category_by_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'test-category']);

        $response = $this->getJson('/api/v1/categories/test-category');

        $response->assertOk()
            ->assertJsonFragment(['slug' => 'test-category']);
    }

    public function test_can_list_products_without_authentication(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'price'],
                ],
            ]);
    }

    public function test_can_view_product_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'test-product']);

        $response = $this->getJson('/api/v1/products/test-product');

        $response->assertOk()
            ->assertJsonFragment(['slug' => 'test-product']);
    }

    public function test_can_get_featured_products(): void
    {
        Product::factory()->count(3)->create(['is_featured' => true]);
        Product::factory()->count(2)->create(['is_featured' => false]);

        $response = $this->getJson('/api/v1/products/featured');

        $response->assertOk();
        $featuredCount = count($response->json('data'));
        $this->assertGreaterThanOrEqual(3, $featuredCount);
    }

    public function test_can_get_public_settings(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_submit_contact_form(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message',
        ]);

        $response->assertStatus(201);
    }

    public function test_contact_form_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/contact', []);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['name', 'email', 'subject', 'message']]]);
    }

    public function test_contact_form_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'subject' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_can_filter_products_by_category(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);
        Product::factory()->count(2)->create();

        $response = $this->getJson("/api/v1/products?category={$category->id}");

        $response->assertOk();
        $products = $response->json('data');
        $this->assertCount(3, $products);
    }

    public function test_can_search_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Apple iPhone']);
        Product::factory()->create(['name' => 'Samsung Galaxy']);
        Product::factory()->create(['name' => 'Apple Watch']);

        $response = $this->getJson('/api/v1/products?search=Apple');

        $response->assertOk();
        $products = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($products));
    }

    public function test_products_pagination_works(): void
    {
        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/products?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'total', 'per_page'],
            ]);
    }
}

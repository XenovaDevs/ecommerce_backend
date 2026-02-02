<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminProductTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_list_products(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'New Product',
            'slug' => 'new-product',
            'description' => 'Product description',
            'price' => 99.99,
            'category_id' => $category->id,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Product']);
    }

    public function test_admin_can_update_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $response = $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Updated Name',
            'price' => 149.99,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_admin_can_delete_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_manager_can_update_but_not_delete_products(): void
    {
        $this->actingAsManager();
        $product = Product::factory()->create();

        // Can update
        $updateResponse = $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Updated',
        ]);
        $this->assertNotEquals(403, $updateResponse->status());

        // Cannot delete
        $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$product->id}");
        $deleteResponse->assertStatus(403);
    }

    public function test_support_cannot_create_or_update_products(): void
    {
        $this->actingAsSupport();
        $category = Category::factory()->create();

        // Cannot create
        $createResponse = $this->postJson('/api/v1/admin/products', [
            'name' => 'New Product',
            'price' => 99.99,
            'category_id' => $category->id,
        ]);
        $createResponse->assertStatus(403);

        // Cannot update
        $product = Product::factory()->create();
        $updateResponse = $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Updated',
        ]);
        $updateResponse->assertStatus(403);
    }

    public function test_support_can_view_products(): void
    {
        $this->actingAsSupport();
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/products');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_product_creation_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/products', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['name', 'price', 'category_id']]]);
    }

    public function test_product_price_must_be_positive(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Test Product',
            'price' => -10,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['price']]]);
    }

    public function test_product_slug_must_be_unique(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['slug' => 'existing-slug']);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'New Product',
            'slug' => 'existing-slug',
            'price' => 99.99,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['slug']]]);
    }

    public function test_admin_can_upload_product_image(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => $file,
        ]);

        $this->assertNotEquals(500, $response->status());
    }

    public function test_manager_can_manage_product_images(): void
    {
        $this->actingAsManager();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => $file,
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_can_filter_products_by_category(): void
    {
        $this->actingAsAdmin();
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $category1->id]);
        Product::factory()->count(2)->create(['category_id' => $category2->id]);

        $response = $this->getJson("/api/v1/admin/products?category={$category1->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_search_products_by_name(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['name' => 'iPhone 13']);
        Product::factory()->create(['name' => 'Samsung Galaxy']);
        Product::factory()->create(['name' => 'iPhone 14']);

        $response = $this->getJson('/api/v1/admin/products?search=iPhone');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }
}

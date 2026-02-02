<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'price'],
                ],
                'pagination',
            ]);
    }

    public function test_can_get_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'slug' => 'test-product',
        ]);

        $response = $this->getJson('/api/v1/products/test-product');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Test Product',
                    'slug' => 'test-product',
                ],
            ]);
    }

    public function test_can_filter_products_by_category(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/products?category_id=' . $category->id);

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_get_featured_products(): void
    {
        Product::factory()->count(3)->featured()->create();
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/products/featured');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }
}

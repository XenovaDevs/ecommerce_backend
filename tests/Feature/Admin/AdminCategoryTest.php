<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_list_categories(): void
    {
        $this->actingAsAdmin();
        Category::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic products',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Electronics']);
    }

    public function test_admin_can_update_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'Updated Category',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Category']);
    }

    public function test_admin_can_delete_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_manager_cannot_create_categories(): void
    {
        $this->actingAsManager();

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_view_categories(): void
    {
        $this->actingAsManager();
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/categories');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_category_slug_must_be_unique(): void
    {
        $this->actingAsAdmin();
        Category::factory()->create(['slug' => 'electronics']);

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics 2',
            'slug' => 'electronics',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.slug.0', 'The slug has already been taken.');
    }

    public function test_category_name_is_required(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/categories', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    }
}

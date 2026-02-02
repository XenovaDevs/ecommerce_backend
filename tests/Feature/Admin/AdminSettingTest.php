<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminSettingTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_view_all_settings(): void
    {
        $this->actingAsAdmin();

        Setting::create(['key' => 'site_name', 'value' => 'My Store', 'is_public' => true]);
        Setting::create(['key' => 'admin_email', 'value' => 'admin@store.com', 'is_public' => false]);

        $response = $this->getJson('/api/v1/admin/settings');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_specific_setting(): void
    {
        $this->actingAsAdmin();

        Setting::create(['key' => 'site_name', 'value' => 'My Store', 'is_public' => true]);

        $response = $this->getJson('/api/v1/admin/settings/site_name');

        $response->assertOk()
            ->assertJsonFragment(['value' => 'My Store']);
    }

    public function test_admin_can_update_settings(): void
    {
        $this->actingAsAdmin();

        Setting::create(['key' => 'site_name', 'value' => 'Old Name', 'is_public' => true]);

        $response = $this->putJson('/api/v1/admin/settings', [
            'settings' => [
                'site_name' => 'New Store Name',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'value' => 'New Store Name',
        ]);
    }

    public function test_manager_cannot_update_settings(): void
    {
        $this->actingAsManager();

        $response = $this->putJson('/api/v1/admin/settings', [
            'settings' => ['site_name' => 'New Name'],
        ]);

        $response->assertStatus(403);
    }

    public function test_support_cannot_view_settings(): void
    {
        $this->actingAsSupport();

        $response = $this->getJson('/api/v1/admin/settings');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_view_admin_settings(): void
    {
        $this->actingAsCustomer();

        $response = $this->getJson('/api/v1/admin/settings');

        $response->assertStatus(403);
    }
}

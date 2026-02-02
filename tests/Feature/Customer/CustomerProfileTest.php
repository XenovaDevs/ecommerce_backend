<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_customer_can_view_profile(): void
    {
        $user = $this->actingAsCustomer([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/v1/customer/profile');

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);
    }

    public function test_customer_can_update_profile(): void
    {
        $this->actingAsCustomer();

        $response = $this->putJson('/api/v1/customer/profile', [
            'name' => 'Updated Name',
            'phone' => '1234567890',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'phone' => '1234567890',
            ]);
    }

    public function test_customer_cannot_update_email_to_existing_email(): void
    {
        $this->actingAsCustomer(['email' => 'customer@example.com']);
        $this->createUser(\App\Domain\Enums\UserRole::CUSTOMER, ['email' => 'other@example.com']);

        $response = $this->putJson('/api/v1/customer/profile', [
            'email' => 'other@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/customer/profile');

        $response->assertStatus(401);
    }

    public function test_admin_cannot_access_customer_profile_endpoint(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/customer/profile');

        $response->assertStatus(403);
    }
}

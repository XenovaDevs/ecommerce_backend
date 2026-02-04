<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;
use Tests\Traits\AssertValidationErrors;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase, AuthHelpers, AssertValidationErrors;

    public function test_customer_can_list_addresses(): void
    {
        $user = $this->actingAsCustomer();

        CustomerAddress::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/customer/addresses');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_customer_can_create_address(): void
    {
        $this->actingAsCustomer();

        $response = $this->postJson('/api/v1/customer/addresses', [
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['city' => 'New York']);
    }

    public function test_customer_can_update_address(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/v1/customer/addresses/{$address->id}", [
            'city' => 'Los Angeles',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['city' => 'Los Angeles']);
    }

    public function test_customer_can_delete_address(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/customer/addresses/{$address->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_customer_can_set_default_address(): void
    {
        $user = $this->actingAsCustomer();
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $response = $this->putJson("/api/v1/customer/addresses/{$address->id}/default");

        $response->assertOk();
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'is_default' => true,
        ]);
    }

    public function test_customer_cannot_access_other_customer_address(): void
    {
        $this->actingAsCustomer();
        $otherUser = $this->createUser(\App\Domain\Enums\UserRole::CUSTOMER);
        $otherAddress = CustomerAddress::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->putJson("/api/v1/customer/addresses/{$otherAddress->id}", [
            'city' => 'Hacked City',
        ]);

        $response->assertStatus(404);
    }

    public function test_address_creation_validates_required_fields(): void
    {
        $this->actingAsCustomer();

        $response = $this->postJson('/api/v1/customer/addresses', []);

        $this->assertCustomValidationErrors($response, [
            'name',
            'phone',
            'address',
            'city',
            'postal_code',
            'country',
        ]);
    }

    public function test_only_one_address_can_be_default_per_type(): void
    {
        $user = $this->actingAsCustomer();

        $address1 = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'type' => 'shipping',
            'is_default' => true,
        ]);

        $address2 = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'type' => 'shipping',
            'is_default' => false,
        ]);

        // Set second address as default
        $this->putJson("/api/v1/customer/addresses/{$address2->id}/default");

        // First address should no longer be default
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address2->id,
            'is_default' => true,
        ]);
    }
}

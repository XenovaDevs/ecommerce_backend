<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AdminContactTest extends TestCase
{
    use RefreshDatabase, AuthHelpers;

    public function test_admin_can_list_contact_messages(): void
    {
        $this->actingAsAdmin();

        ContactMessage::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/contacts');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_view_specific_contact_message(): void
    {
        $this->actingAsAdmin();

        $message = ContactMessage::factory()->create([
            'subject' => 'Test Subject',
        ]);

        $response = $this->getJson("/api/v1/admin/contacts/{$message->id}");

        $response->assertOk()
            ->assertJsonFragment(['subject' => 'Test Subject']);
    }

    public function test_admin_can_reply_to_contact_message(): void
    {
        $this->actingAsAdmin();

        $message = ContactMessage::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/v1/admin/contacts/{$message->id}/reply", [
            'reply' => 'Thank you for contacting us.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'reply' => 'Thank you for contacting us.',
            'status' => 'replied',
        ]);
    }

    public function test_admin_can_update_contact_message_status(): void
    {
        $this->actingAsAdmin();

        $message = ContactMessage::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/v1/admin/contacts/{$message->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'closed',
        ]);
    }

    public function test_manager_cannot_reply_to_contact_messages(): void
    {
        $this->actingAsManager();

        $message = ContactMessage::factory()->create();

        $response = $this->putJson("/api/v1/admin/contacts/{$message->id}/reply", [
            'reply' => 'Test reply',
        ]);

        $response->assertStatus(403);
    }

    public function test_support_cannot_access_contact_messages(): void
    {
        $this->actingAsSupport();

        $response = $this->getJson('/api/v1/admin/contacts');

        $response->assertStatus(403);
    }

    public function test_can_filter_contact_messages_by_status(): void
    {
        $this->actingAsAdmin();

        ContactMessage::factory()->count(3)->create(['status' => 'pending']);
        ContactMessage::factory()->count(2)->create(['status' => 'replied']);

        $response = $this->getJson('/api/v1/admin/contacts?status=pending');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_contact_messages_are_paginated(): void
    {
        $this->actingAsAdmin();

        ContactMessage::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/admin/contacts?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'pagination' => ['current_page', 'total', 'per_page'],
            ]);
    }

    public function test_reply_is_required_when_replying(): void
    {
        $this->actingAsAdmin();

        $message = ContactMessage::factory()->create();

        $response = $this->putJson("/api/v1/admin/contacts/{$message->id}/reply", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['reply']]]);
    }
}

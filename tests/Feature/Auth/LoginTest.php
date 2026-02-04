<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertValidationErrors;

class LoginTest extends TestCase
{
    use RefreshDatabase, AssertValidationErrors;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
    }
}

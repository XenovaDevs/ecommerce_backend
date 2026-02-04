<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertValidationErrors;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase, AssertValidationErrors;

    public function test_complete_authentication_flow(): void
    {
        // Register a new user
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user'],
            ]);

        $accessToken = $registerResponse->json('data.access_token');
        $refreshToken = $registerResponse->json('data.refresh_token');

        // Get authenticated user info
        $meResponse = $this->withToken($accessToken)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonFragment(['email' => 'test@example.com']);

        // Logout
        $logoutResponse = $this->withToken($accessToken)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk();

        // Verify token is invalidated
        $meAfterLogout = $this->withToken($accessToken)
            ->getJson('/api/v1/auth/me');

        $meAfterLogout->assertUnauthorized();

        // Login again
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
            ]);
    }

    public function test_refresh_token_works(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
            ]);
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_login_with_inactive_account_fails(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_password_must_be_confirmed_on_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['password']]]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AssertValidationErrors;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase, AssertValidationErrors;

    private const VALID_PASSWORD = 'S9!xQ2#pL7@t';

    public function test_complete_authentication_flow(): void
    {
        // Register a new user
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'token_type', 'expires_in', 'user'],
            ]);

        $accessToken = $registerResponse->json('data.access_token');

        $registerResponse->assertCookie(config('auth.refresh_cookie_name', 'refresh_token'));

        // Get authenticated user info
        $meResponse = $this->withToken($accessToken)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonFragment(['email' => 'test@example.com']);

        // Logout
        $logoutResponse = $this->withToken($accessToken)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk();

        // Verify token is invalidated in storage
        $accessTokenId = (int) explode('|', $accessToken)[0];
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessTokenId,
        ]);

        // Login again
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'token_type', 'expires_in'],
            ]);
        $loginResponse->assertCookie(config('auth.refresh_cookie_name', 'refresh_token'));
    }

    public function test_refresh_token_works(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt(self::VALID_PASSWORD),
        ]);

        $plainRefreshToken = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainRefreshToken),
            'revoked' => false,
            'expires_at' => now()->addDays(7),
        ]);

        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $plainRefreshToken,
        ]);

        $refreshResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'token_type', 'expires_in'],
            ]);
        $refreshResponse->assertCookie(config('auth.refresh_cookie_name', 'refresh_token'));
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_login_with_inactive_account_fails(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt(self::VALID_PASSWORD),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::VALID_PASSWORD,
        ]);

        $response->assertStatus(401);
    }

    public function test_password_must_be_confirmed_on_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['password']]]);
    }
}

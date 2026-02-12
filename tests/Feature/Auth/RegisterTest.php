<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertValidationErrors;

class RegisterTest extends TestCase
{
    use RefreshDatabase, AssertValidationErrors;

    private const VALID_PASSWORD = 'S9!xQ2#pL7@t';

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $response->assertCookie(config('auth.refresh_cookie_name', 'refresh_token'));

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['password']]]);
    }
}

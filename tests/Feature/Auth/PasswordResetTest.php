<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * @ai-context Feature tests for password reset flow.
 *             Tests the complete flow from API endpoints.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_forgot_password_returns_success_for_nonexistent_email(): void
    {
        // For security reasons, we don't want to reveal which emails exist
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable();
    }

    public function test_forgot_password_rate_limiting(): void
    {
        $user = User::factory()->create();

        // First request should succeed
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        // Second request within 60 seconds should be rate limited
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('OldP@ssw0rd!'),
        ]);

        $plainToken = 'test-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $newPassword = 'NewSecureP@ssw0rd!123';

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Verify all tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewP@ssw0rd!123',
            'password_confirmation' => 'NewP@ssw0rd!123',
        ]);

        $response->assertStatus(400);
    }

    public function test_reset_password_with_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $plainToken = 'expired-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now()->subMinutes(61), // Expired (60 min TTL)
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'NewP@ssw0rd!123',
            'password_confirmation' => 'NewP@ssw0rd!123',
        ]);

        $response->assertStatus(400);
    }

    public function test_reset_password_validates_password_strength(): void
    {
        $user = User::factory()->create();

        $plainToken = 'test-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $plainToken = 'test-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'ValidP@ssw0rd!123',
            'password_confirmation' => 'DifferentP@ssw0rd!123',
        ]);

        $response->assertUnprocessable();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Exceptions\User\UserNotFoundException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordResetService $service;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = app(UserRepositoryInterface::class);
        $this->service = new PasswordResetService($this->userRepository);

        Notification::fake();
    }

    public function test_send_reset_link_creates_token_and_sends_notification(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->service->sendResetLink($user->email);

        // Assert token was created in database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Assert notification was sent
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_send_reset_link_throws_exception_for_nonexistent_user(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->sendResetLink('nonexistent@example.com');
    }

    public function test_send_reset_link_deletes_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Create an old token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', 'old-token'),
            'created_at' => now()->subHours(2),
        ]);

        $this->service->sendResetLink($user->email);

        // Assert only one token exists (the new one)
        $this->assertEquals(1, DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->count());
    }

    public function test_validate_token_returns_true_for_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $plainToken = 'test-token-123';

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $result = $this->service->validateToken($user->email, $plainToken);

        $this->assertTrue($result);
    }

    public function test_validate_token_returns_false_for_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $plainToken = 'test-token-123';

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now()->subMinutes(61), // Expired (60 min TTL)
        ]);

        $result = $this->service->validateToken($user->email, $plainToken);

        $this->assertFalse($result);
    }

    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $result = $this->service->validateToken($user->email, 'invalid-token');

        $this->assertFalse($result);
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $plainToken = 'test-token-123';
        $newPassword = 'NewSecureP@ssw0rd!';

        // Create access token
        $user->createToken('test-token');

        // Create reset token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $result = $this->service->resetPassword(
            $user->email,
            $plainToken,
            $newPassword
        );

        $this->assertTrue($result);

        // Assert password was updated
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        // Assert reset token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Assert all tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_reset_password_throws_exception_for_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(InvalidPasswordResetTokenException::class);

        $this->service->resetPassword(
            $user->email,
            'invalid-token',
            'NewP@ssw0rd!'
        );
    }

    public function test_reset_password_throws_exception_for_nonexistent_user(): void
    {
        $plainToken = 'test-token-123';

        DB::table('password_reset_tokens')->insert([
            'email' => 'nonexistent@example.com',
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $this->expectException(UserNotFoundException::class);

        $this->service->resetPassword(
            'nonexistent@example.com',
            $plainToken,
            'NewP@ssw0rd!'
        );
    }
}

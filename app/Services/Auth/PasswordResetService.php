<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Exceptions\User\UserNotFoundException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Constants\SecurityConstants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @ai-context PasswordResetService handles password reset operations.
 *             Follows Single Responsibility Principle by managing only password reset logic.
 *             Uses Dependency Injection for loose coupling (DIP).
 *
 * @ai-security Token is hashed before storage to prevent token theft from database breaches.
 *              Tokens expire after 60 minutes as defined in SecurityConstants.
 */
class PasswordResetService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Send password reset link to the user's email.
     *
     * @throws UserNotFoundException When user doesn't exist
     */
    public function sendResetLink(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new UserNotFoundException();
        }

        // Delete any existing tokens for this email
        $this->deleteExistingTokens($email);

        // Generate a secure random token
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        // Store hashed token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        // Send notification with plain token (not hashed)
        $user->notify(new ResetPasswordNotification($plainToken, $email));
    }

    /**
     * Validate password reset token.
     *
     * @param string $email User's email address
     * @param string $token Plain token from reset link
     * @return bool True if token is valid and not expired
     */
    public function validateToken(string $email, string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return false;
        }

        // Check if token is expired
        $expiresAt = now()->subMinutes(SecurityConstants::PASSWORD_RESET_TTL);

        return $record->created_at >= $expiresAt;
    }

    /**
     * Reset user's password using token.
     *
     * @throws InvalidPasswordResetTokenException When token is invalid or expired
     * @throws UserNotFoundException When user doesn't exist
     */
    public function resetPassword(string $email, string $token, string $password): bool
    {
        // Validate token first
        if (!$this->validateToken($email, $token)) {
            throw new InvalidPasswordResetTokenException();
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new UserNotFoundException();
        }

        // Update password
        $this->userRepository->update($user, [
            'password' => Hash::make($password),
        ]);

        // Revoke all existing tokens for security
        $this->revokeAllUserTokens($user);

        // Delete password reset token
        $this->deleteExistingTokens($email);

        return true;
    }

    /**
     * Delete existing password reset tokens for an email.
     * Prevents token reuse and cleans up old tokens.
     */
    private function deleteExistingTokens(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    /**
     * Revoke all authentication tokens for a user.
     * Security measure to force re-authentication after password change.
     */
    private function revokeAllUserTokens(User $user): void
    {
        // Revoke Sanctum access tokens
        $user->tokens()->delete();

        // Revoke refresh tokens
        $user->refreshTokens()->update(['revoked' => true]);
    }
}

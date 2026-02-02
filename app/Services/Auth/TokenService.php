<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\Auth\AuthResponseDTO;
use App\Models\User;
use App\Models\RefreshToken;
use App\Support\Constants\SecurityConstants;
use App\Support\Authorization\RolePermissions;
use App\Exceptions\Auth\TokenExpiredException;
use Illuminate\Support\Str;

/**
 * @ai-context Service for managing authentication tokens.
 *             Handles creation, validation, and revocation of access and refresh tokens.
 * @ai-dependencies
 *   - Laravel Sanctum for access tokens
 *   - RefreshToken model for refresh token storage
 * @ai-security
 *   - Refresh tokens are hashed before storage
 *   - Tokens have configurable expiration
 */
class TokenService
{
    /**
     * Create a token pair (access + refresh) for a user.
     */
    public function createTokenPair(User $user): AuthResponseDTO
    {
        // Revoke existing tokens if needed
        $user->tokens()->delete();

        // Create access token with Sanctum
        $accessToken = $user->createToken(
            name: 'access-token',
            abilities: $this->getAbilitiesForUser($user),
            expiresAt: now()->addMinutes(SecurityConstants::ACCESS_TOKEN_TTL)
        );

        // Create refresh token
        $plainRefreshToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(SecurityConstants::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return new AuthResponseDTO(
            accessToken: $accessToken->plainTextToken,
            tokenType: 'Bearer',
            expiresIn: SecurityConstants::ACCESS_TOKEN_TTL * 60, // Convert to seconds
            refreshToken: $plainRefreshToken,
            user: $user,
        );
    }

    /**
     * Refresh tokens using a refresh token.
     *
     * @throws TokenExpiredException
     */
    public function refreshTokens(string $refreshToken): AuthResponseDTO
    {
        $hashedToken = hash('sha256', $refreshToken);

        $storedToken = RefreshToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->where('revoked', false)
            ->first();

        if (!$storedToken) {
            throw new TokenExpiredException();
        }

        // Revoke the used refresh token
        $storedToken->update(['revoked' => true]);

        // Get the user
        $user = $storedToken->user;

        // Create new token pair
        return $this->createTokenPair($user);
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(User $user): void
    {
        // Revoke Sanctum tokens
        $user->tokens()->delete();

        // Revoke refresh tokens
        RefreshToken::where('user_id', $user->id)
            ->where('revoked', false)
            ->update(['revoked' => true]);
    }

    /**
     * Get abilities/permissions based on user role.
     *
     * @return array<string>
     */
    private function getAbilitiesForUser(User $user): array
    {
        return RolePermissions::getAbilitiesForRole($user->role);
    }
}

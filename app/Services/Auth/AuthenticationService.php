<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Domain\Enums\UserRole;
use App\DTOs\Auth\AuthResponseDTO;
use App\DTOs\Auth\LoginRequestDTO;
use App\DTOs\Auth\RegisterRequestDTO;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TokenExpiredException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Constants\SecurityConstants;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @ai-context AuthenticationService handles all authentication flows.
 */
class AuthenticationService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function register(RegisterRequestDTO $dto): AuthResponseDTO
    {
        $user = $this->userRepository->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'phone' => $dto->phone,
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        return $this->createAuthResponse($user);
    }

    public function login(LoginRequestDTO $dto): AuthResponseDTO
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user || !Hash::check($dto->password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        if (!$user->is_active) {
            throw new InvalidCredentialsException();
        }

        return $this->createAuthResponse($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
        $user->refreshTokens()->where('revoked', false)->update(['revoked' => true]);
    }

    public function refresh(string $refreshToken): AuthResponseDTO
    {
        $hashedToken = hash('sha256', $refreshToken);
        $token = RefreshToken::where('token', $hashedToken)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            throw new TokenExpiredException();
        }

        $user = $token->user;

        if (!$user->is_active) {
            throw new InvalidCredentialsException();
        }

        $token->revoke();

        return $this->createAuthResponse($user);
    }

    private function createAuthResponse(User $user): AuthResponseDTO
    {
        $accessToken = $user->createToken(
            'access-token',
            ['*'],
            now()->addMinutes(SecurityConstants::ACCESS_TOKEN_TTL)
        );

        $plainRefreshToken = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(SecurityConstants::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return new AuthResponseDTO(
            user: $user,
            accessToken: $accessToken->plainTextToken,
            refreshToken: $plainRefreshToken,
            expiresIn: SecurityConstants::ACCESS_TOKEN_TTL * 60,
        );
    }
}

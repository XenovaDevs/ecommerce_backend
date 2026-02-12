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
    private const ADMIN_ABILITIES = [
        'dashboard.view',
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'products.manage-images',
        'orders.view-all',
        'orders.view-own',
        'orders.create',
        'orders.update-status',
        'orders.delete',
        'orders.cancel',
        'orders.create-shipment',
        'customers.view',
        'settings.view',
        'settings.update',
        'reports.view-sales',
        'reports.view-products',
        'reports.view-customers',
        'contacts.view',
        'contacts.reply',
        'contacts.update-status',
        'reviews.manage',
    ];

    private const CUSTOMER_ABILITIES = [
        'orders.view-own',
        'orders.create',
        'orders.cancel',
        'wishlist.manage',
        'reviews.create',
        'reviews.update-own',
        'reviews.delete-own',
    ];

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
        $user->tokens()->delete();

        $user->refreshTokens()->where('revoked', false)->update(['revoked' => true]);
    }

    public function refresh(?string $refreshToken): AuthResponseDTO
    {
        if (!$refreshToken) {
            throw new TokenExpiredException();
        }

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
        $abilities = $this->getAbilitiesForRole($user->role);

        $accessToken = $user->createToken(
            'access-token',
            $abilities,
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

    /**
     * @return array<int, string>
     */
    private function getAbilitiesForRole(UserRole $role): array
    {
        if ($role->isStaff()) {
            return self::ADMIN_ABILITIES;
        }

        return self::CUSTOMER_ABILITIES;
    }
}

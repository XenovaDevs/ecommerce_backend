<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\User;

final readonly class AuthResponseDTO
{
    public function __construct(
        public User $user,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}

    public function toArray(bool $includeRefreshToken = true): array
    {
        $payload = [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'role' => $this->user->role->value,
            ],
            'access_token' => $this->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expiresIn,
        ];

        if ($includeRefreshToken) {
            $payload['refresh_token'] = $this->refreshToken;
        }

        return $payload;
    }
}

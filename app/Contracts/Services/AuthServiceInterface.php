<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Auth\AuthResponseDTO;
use App\DTOs\Auth\LoginRequestDTO;
use App\DTOs\Auth\RegisterRequestDTO;
use App\Models\User;

/**
 * @ai-context Interface for authentication service operations.
 */
interface AuthServiceInterface
{
    public function register(RegisterRequestDTO $dto): AuthResponseDTO;

    public function login(LoginRequestDTO $dto): AuthResponseDTO;

    public function logout(User $user): void;

    public function refresh(string $refreshToken): AuthResponseDTO;
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\AuthServiceInterface;
use App\DTOs\Auth\LoginRequestDTO;
use App\DTOs\Auth\RegisterRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Messages\SuccessMessages;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AuthController handles authentication API endpoints.
 */
class AuthController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $dto = RegisterRequestDTO::fromRequest($request);
        $response = $this->authService->register($dto);

        return $this->created(
            $response->toArray(),
            SuccessMessages::AUTH['REGISTERED']
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginRequestDTO::fromRequest($request);
        $response = $this->authService->login($dto);

        return $this->success(
            $response->toArray(),
            SuccessMessages::AUTH['LOGIN']
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(
            null,
            SuccessMessages::AUTH['LOGOUT']
        );
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $response = $this->authService->refresh($request->validated('refresh_token'));

        return $this->success($response->toArray());
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }
}

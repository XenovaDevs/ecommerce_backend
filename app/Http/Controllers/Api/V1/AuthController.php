<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\AuthServiceInterface;
use App\DTOs\Auth\LoginRequestDTO;
use App\DTOs\Auth\RegisterRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Messages\SuccessMessages;
use App\Services\Auth\PasswordResetService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * @ai-context AuthController handles authentication API endpoints.
 */
class AuthController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly PasswordResetService $passwordResetService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $dto = RegisterRequestDTO::fromRequest($request);
        $response = $this->authService->register($dto);
        $jsonResponse = $this->created(
            $response->toArray(includeRefreshToken: false),
            SuccessMessages::AUTH['REGISTERED']
        );

        return $this->withRefreshCookie($jsonResponse, $response->refreshToken);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginRequestDTO::fromRequest($request);
        $response = $this->authService->login($dto);
        $jsonResponse = $this->success(
            $response->toArray(includeRefreshToken: false),
            SuccessMessages::AUTH['LOGIN']
        );

        return $this->withRefreshCookie($jsonResponse, $response->refreshToken);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        $jsonResponse = $this->success(
            null,
            SuccessMessages::AUTH['LOGOUT']
        );

        return $this->clearRefreshCookie($jsonResponse);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie($this->refreshCookieName())
            ?? $request->input('refresh_token');
        $response = $this->authService->refresh($refreshToken);

        $jsonResponse = $this->success(
            $response->toArray(includeRefreshToken: false),
            SuccessMessages::AUTH['TOKEN_REFRESHED']
        );

        return $this->withRefreshCookie($jsonResponse, $response->refreshToken);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /**
     * Send password reset link to user's email.
     * Rate limited to 1 request per 60 seconds per email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->success(
            null,
            SuccessMessages::AUTH['PASSWORD_RESET_SENT']
        );
    }

    /**
     * Reset user's password using token from email.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->passwordResetService->resetPassword(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password']
        );

        return $this->success(
            null,
            SuccessMessages::AUTH['PASSWORD_RESET']
        );
    }

    private function withRefreshCookie(JsonResponse $response, string $refreshToken): JsonResponse
    {
        $minutes = (int) config('auth.refresh_token_ttl_minutes');

        return $response->cookie(Cookie::make(
            name: $this->refreshCookieName(),
            value: $refreshToken,
            minutes: $minutes,
            path: config('auth.refresh_cookie_path'),
            domain: config('auth.refresh_cookie_domain'),
            secure: (bool) config('auth.refresh_cookie_secure'),
            httpOnly: true,
            raw: false,
            sameSite: config('auth.refresh_cookie_same_site')
        ));
    }

    private function clearRefreshCookie(JsonResponse $response): JsonResponse
    {
        $forgetCookie = Cookie::forget(
            name: $this->refreshCookieName(),
            path: config('auth.refresh_cookie_path'),
            domain: config('auth.refresh_cookie_domain')
        );
        return $response->withoutCookie($forgetCookie);
    }

    private function refreshCookieName(): string
    {
        return (string) config('auth.refresh_cookie_name', 'refresh_token');
    }
}

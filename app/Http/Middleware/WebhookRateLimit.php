<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookRateLimit
{
    public function __construct(
        private readonly RateLimiter $limiter
    ) {}

    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = 'webhook:' . $request->ip();

        if ($this->limiter->tooManyAttempts($key, (int) $maxAttempts)) {
            return response()->json([
                'error' => 'Too many webhook requests',
            ], 429);
        }

        $this->limiter->hit($key, (int) $decayMinutes * 60);

        return $next($request);
    }
}

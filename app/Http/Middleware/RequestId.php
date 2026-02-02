<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ai-context Adds a unique request ID to each request for tracing and debugging.
 *             The ID is added to both request headers and response headers.
 * @ai-flow
 *   1. Request comes in -> 2. Generate or use existing X-Request-ID
 *   3. Add to request headers -> 4. Process request
 *   5. Add to response headers -> 6. Return response
 * @ai-dependencies None
 */
class RequestId
{
    public const HEADER_NAME = 'X-Request-ID';

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use existing request ID from header or generate new one
        $requestId = $request->header(self::HEADER_NAME) ?? $this->generateRequestId();

        // Set the request ID on the incoming request
        $request->headers->set(self::HEADER_NAME, $requestId);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }

    /**
     * Generate a unique request ID.
     */
    private function generateRequestId(): string
    {
        return (string) Str::uuid();
    }
}

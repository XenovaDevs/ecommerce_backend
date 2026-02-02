<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ai-context Forces all API responses to be JSON.
 *             Also sets Accept header if not present to ensure proper content negotiation.
 * @ai-flow
 *   1. Request comes in -> 2. Sets Accept header to JSON
 *   3. Passes to next middleware -> 4. Returns response
 */
class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force Accept header to application/json if not set or is wildcard
        if (!$request->wantsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}

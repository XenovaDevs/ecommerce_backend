<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds shared-cache friendly headers to public read endpoints and supports ETag validation.
 */
class PublicApiCacheHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($response->headers->has('Set-Cookie')) {
            return $response;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        [$ttl, $swr] = $this->resolveTtl($request);

        $response->headers->set('Cache-Control', sprintf(
            'public, max-age=0, s-maxage=%d, stale-while-revalidate=%d',
            $ttl,
            $swr
        ));
        $response->headers->set('Vary', 'Accept-Encoding');

        if (!$response->headers->has('ETag')) {
            $response->setEtag($this->buildStableEtag($response));
        }

        $response->isNotModified($request);

        return $response;
    }

    /**
     * Resolve TTL policy based on endpoint type.
     *
     * @return array{0:int,1:int}
     */
    private function resolveTtl(Request $request): array
    {
        $path = ltrim($request->path(), '/');
        $defaults = (array) config('performance.public_http_cache', []);

        if (str_starts_with($path, 'api/v1/settings/public')) {
            return [(int) ($defaults['settings'] ?? 300), (int) ($defaults['settings_swr'] ?? 600)];
        }

        if (str_starts_with($path, 'api/v1/categories')) {
            return [(int) ($defaults['categories'] ?? 300), (int) ($defaults['categories_swr'] ?? 600)];
        }

        if (str_starts_with($path, 'api/v1/products/featured')
            || str_contains($path, '/related')
            || preg_match('#^api/v1/products/[^/]+$#', $path) === 1
        ) {
            return [(int) ($defaults['product_detail'] ?? 300), (int) ($defaults['product_detail_swr'] ?? 600)];
        }

        if (str_starts_with($path, 'api/v1/products')) {
            return [(int) ($defaults['products'] ?? 120), (int) ($defaults['products_swr'] ?? 300)];
        }

        if (str_starts_with($path, 'api/v1/reviews')) {
            return [(int) ($defaults['reviews'] ?? 180), (int) ($defaults['reviews_swr'] ?? 300)];
        }

        return [(int) ($defaults['default'] ?? 120), (int) ($defaults['default_swr'] ?? 300)];
    }

    /**
     * Build a stable ETag by removing volatile metadata keys from JSON payloads.
     */
    private function buildStableEtag(Response $response): string
    {
        $content = (string) $response->getContent();
        $contentType = (string) $response->headers->get('Content-Type');

        if (!str_contains(strtolower($contentType), 'json')) {
            return sha1($content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return sha1($content);
        }

        if (isset($decoded['meta']) && is_array($decoded['meta'])) {
            unset($decoded['meta']['timestamp'], $decoded['meta']['request_id']);
        }

        return sha1((string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

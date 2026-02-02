<?php

declare(strict_types=1);

namespace App\Support\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @ai-context Provides standardized API response methods.
 *             ALL controllers MUST use this trait for consistent responses.
 * @ai-flow
 *   1. Controller method calls success/error/paginated
 *   2. Method builds standardized response structure
 *   3. Returns JsonResponse with proper status code
 */
trait HasApiResponse
{
    /**
     * Return a success response.
     *
     * @param mixed $data Response data (can be array, JsonResource, or null)
     * @param string|null $message Optional success message
     * @param int $status HTTP status code (default 200)
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => $this->getMeta(),
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a created response (HTTP 201).
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     */
    protected function created(
        mixed $data = null,
        ?string $message = null
    ): JsonResponse {
        return $this->success($data, $message, 201);
    }

    /**
     * Return a no content response (HTTP 204).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a paginated response with pagination metadata.
     *
     * @param ResourceCollection $collection Paginated resource collection
     */
    protected function paginated(ResourceCollection $collection): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
            'success' => true,
            'data' => $collection->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'meta' => $this->getMeta(),
        ]);
    }

    /**
     * Return an error response.
     *
     * @param string $message Error message
     * @param string $code Error code for client-side handling
     * @param int $status HTTP status code (default 400)
     * @param array $details Additional error details
     */
    protected function error(
        string $message,
        string $code,
        int $status = 400,
        array $details = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => $this->getMeta(),
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a validation error response (HTTP 422).
     *
     * @param array $errors Validation errors array
     * @param string $message Error message
     */
    protected function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
                'details' => $errors,
            ],
            'meta' => $this->getMeta(),
        ], 422);
    }

    /**
     * Return a not found error response (HTTP 404).
     *
     * @param string $message Error message
     * @param string $code Error code
     */
    protected function notFound(
        string $message = 'Resource not found',
        string $code = 'NOT_FOUND'
    ): JsonResponse {
        return $this->error($message, $code, 404);
    }

    /**
     * Return an unauthorized error response (HTTP 401).
     *
     * @param string $message Error message
     * @param string $code Error code
     */
    protected function unauthorized(
        string $message = 'Unauthorized',
        string $code = 'UNAUTHORIZED'
    ): JsonResponse {
        return $this->error($message, $code, 401);
    }

    /**
     * Return a forbidden error response (HTTP 403).
     *
     * @param string $message Error message
     * @param string $code Error code
     */
    protected function forbidden(
        string $message = 'Access denied',
        string $code = 'FORBIDDEN'
    ): JsonResponse {
        return $this->error($message, $code, 403);
    }

    /**
     * Get response metadata.
     *
     * @return array<string, mixed>
     */
    private function getMeta(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'request_id' => request()->header('X-Request-ID'),
        ];
    }
}

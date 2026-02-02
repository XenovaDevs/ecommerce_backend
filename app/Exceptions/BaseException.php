<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * @ai-context Base exception class for all custom exceptions.
 *             Provides consistent error structure across the application.
 *             ALL custom exceptions MUST extend this class.
 * @ai-flow
 *   1. Exception is thrown -> 2. Handler catches it -> 3. render() returns JSON
 * @ai-security
 *   - Metadata is filtered before output to prevent sensitive data leakage
 *   - Stack traces are only included in debug mode
 */
abstract class BaseException extends Exception
{
    /**
     * Additional metadata about the exception.
     */
    protected array $metadata = [];

    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message,
        array $metadata = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->metadata = $metadata;
    }

    /**
     * Get the unique error code for this exception type.
     * Used for client-side error handling and i18n.
     */
    abstract public function getErrorCode(): string;

    /**
     * Get the HTTP status code for this exception.
     */
    abstract public function getHttpStatus(): int;

    /**
     * Determine if this is an operational error (expected) or programming error.
     * Operational errors are logged at info level, programming errors at error level.
     */
    abstract public function isOperational(): bool;

    /**
     * Get the exception metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set additional metadata.
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getMessage(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->header('X-Request-ID'),
            ],
        ];

        // Only include metadata if not empty and not in production
        if (!empty($this->metadata) && config('app.debug')) {
            $response['error']['details'] = $this->filterSensitiveMetadata($this->metadata);
        }

        return response()->json($response, $this->getHttpStatus());
    }

    /**
     * Filter sensitive data from metadata before output.
     */
    protected function filterSensitiveMetadata(array $metadata): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie'];

        return collect($metadata)
            ->reject(fn ($value, $key) => in_array(strtolower($key), $sensitiveKeys))
            ->toArray();
    }
}

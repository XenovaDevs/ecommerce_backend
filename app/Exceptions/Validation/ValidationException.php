<?php

declare(strict_types=1);

namespace App\Exceptions\Validation;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;
use Illuminate\Http\JsonResponse;

/**
 * @ai-context Custom validation exception for consistent validation error responses.
 * @ai-flow Thrown by FormRequests or manually -> Caught by handler -> Returns 422
 */
class ValidationException extends BaseException
{
    /**
     * @var array<string, array<string>> Validation errors by field
     */
    private array $errors;

    /**
     * Create a new exception instance.
     *
     * @param array<string, array<string>> $errors Validation errors
     * @param string|null $message Custom message
     */
    public function __construct(array $errors, ?string $message = null)
    {
        $this->errors = $errors;
        parent::__construct($message ?? ErrorMessages::GENERAL['VALIDATION_FAILED']);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function getHttpStatus(): int
    {
        return 422;
    }

    public function isOperational(): bool
    {
        return true;
    }

    /**
     * Render the exception with validation errors.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getMessage(),
                'details' => $this->errors,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->header('X-Request-ID'),
            ],
        ], $this->getHttpStatus());
    }
}

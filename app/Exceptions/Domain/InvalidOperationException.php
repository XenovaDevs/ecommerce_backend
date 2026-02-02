<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when an operation cannot be performed due to current state.
 *             Example: Cannot cancel an already shipped order.
 * @ai-flow Thrown by services -> Caught by exception handler -> Returns 409 Conflict
 */
class InvalidOperationException extends BaseException
{
    private string $errorCode;

    /**
     * Create a new exception instance.
     *
     * @param string $message User-friendly error message
     * @param string $errorCode Specific error code for this operation
     * @param array $metadata Additional context
     */
    public function __construct(
        string $message,
        string $errorCode = 'INVALID_OPERATION',
        array $metadata = []
    ) {
        parent::__construct($message, $metadata);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return 409;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

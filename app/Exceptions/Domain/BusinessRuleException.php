<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when a business rule is violated.
 *             Use this for domain-level validation failures.
 * @ai-flow Thrown by services/domain logic -> Caught by exception handler -> Returns 422
 */
class BusinessRuleException extends BaseException
{
    private string $errorCode;

    /**
     * Create a new exception instance.
     *
     * @param string $message User-friendly error message
     * @param string $errorCode Specific error code for this rule violation
     * @param array $metadata Additional context about the violation
     */
    public function __construct(
        string $message,
        string $errorCode = 'BUSINESS_RULE_VIOLATION',
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
        return 422;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

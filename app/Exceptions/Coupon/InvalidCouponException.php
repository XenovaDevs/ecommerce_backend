<?php

declare(strict_types=1);

namespace App\Exceptions\Coupon;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when a coupon is invalid or cannot be applied.
 *             Examples: expired coupon, inactive coupon, minimum amount not met.
 * @ai-flow Thrown by CouponService -> Caught by exception handler -> Returns 422 Unprocessable Entity
 */
class InvalidCouponException extends BaseException
{
    private string $errorCode;

    /**
     * Create a new exception instance.
     *
     * @param string $message User-friendly error message
     * @param string $errorCode Specific error code for this coupon validation failure
     * @param array $metadata Additional context about the error
     */
    public function __construct(
        string $message,
        string $errorCode = 'INVALID_COUPON',
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
        return 422; // Unprocessable Entity
    }

    public function isOperational(): bool
    {
        return true; // This is an expected business rule violation
    }
}

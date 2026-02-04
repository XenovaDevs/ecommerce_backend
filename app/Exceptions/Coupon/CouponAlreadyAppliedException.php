<?php

declare(strict_types=1);

namespace App\Exceptions\Coupon;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when attempting to apply a coupon that is already applied to the cart.
 *             Prevents duplicate coupon application.
 * @ai-flow Thrown by CouponService -> Caught by exception handler -> Returns 409 Conflict
 */
class CouponAlreadyAppliedException extends BaseException
{
    /**
     * Create a new exception instance.
     *
     * @param string $message User-friendly error message
     * @param array $metadata Additional context about the error
     */
    public function __construct(
        string $message = 'This coupon has already been applied to your cart',
        array $metadata = []
    ) {
        parent::__construct($message, $metadata);
    }

    public function getErrorCode(): string
    {
        return 'COUPON_ALREADY_APPLIED';
    }

    public function getHttpStatus(): int
    {
        return 409; // Conflict
    }

    public function isOperational(): bool
    {
        return true; // This is an expected business rule violation
    }
}

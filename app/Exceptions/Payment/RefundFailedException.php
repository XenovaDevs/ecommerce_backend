<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when a refund processing fails.
 * @ai-flow Thrown by PaymentService -> Caught by handler -> Returns 422
 */
class RefundFailedException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::PAYMENT['REFUND_FAILED'],
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'REFUND_FAILED';
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

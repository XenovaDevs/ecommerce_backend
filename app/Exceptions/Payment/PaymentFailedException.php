<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when a payment processing fails.
 * @ai-security Never expose detailed payment gateway errors to client
 * @ai-flow Thrown by PaymentService -> Caught by handler -> Returns 422
 */
class PaymentFailedException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::PAYMENT['FAILED'],
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_FAILED';
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

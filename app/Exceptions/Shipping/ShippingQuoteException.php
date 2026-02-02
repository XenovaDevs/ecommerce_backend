<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when shipping quote calculation fails.
 * @ai-flow Thrown by ShippingService -> Caught by handler -> Returns 422
 */
class ShippingQuoteException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::SHIPPING['QUOTE_FAILED'],
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'SHIPPING_QUOTE_FAILED';
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

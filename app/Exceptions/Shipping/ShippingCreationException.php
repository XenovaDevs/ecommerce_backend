<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when shipment creation fails.
 * @ai-flow Thrown by ShippingService -> Caught by handler -> Returns 422
 */
class ShippingCreationException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::SHIPPING['CREATION_FAILED'],
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'SHIPPING_CREATION_FAILED';
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

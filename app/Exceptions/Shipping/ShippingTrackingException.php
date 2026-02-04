<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * Exception thrown when shipment tracking fails.
 * Single Responsibility: Handle tracking-related errors with proper metadata.
 *
 * @ai-context Exception thrown when tracking information cannot be retrieved
 * @ai-flow Thrown by ShippingService -> Caught by handler -> Returns 404
 */
class ShippingTrackingException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::SHIPPING['TRACKING_FAILED'],
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'SHIPPING_TRACKING_FAILED';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

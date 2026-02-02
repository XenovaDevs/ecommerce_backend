<?php

declare(strict_types=1);

namespace App\Services\Shipping\Exceptions;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * Exception thrown when shipment tracking fails.
 */
class ShippingTrackingException extends BaseException
{
    public function __construct(?string $reason = null, array $metadata = [])
    {
        parent::__construct(
            ErrorMessages::SHIPPING['TRACKING_FAILED'] ?? 'Tracking failed',
            array_merge(['reason' => $reason], $metadata)
        );
    }

    public function getErrorCode(): string
    {
        return 'SHIPPING_TRACKING_FAILED';
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

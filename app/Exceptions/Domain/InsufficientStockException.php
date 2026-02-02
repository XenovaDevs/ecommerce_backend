<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when there is not enough stock to fulfill a request.
 *             Used in cart additions, order processing, etc.
 * @ai-flow Thrown by stock/cart services -> Caught by exception handler -> Returns 422
 */
class InsufficientStockException extends BaseException
{
    /**
     * Create a new exception instance.
     *
     * @param string $productName Name of the product
     * @param int $requested Quantity requested
     * @param int $available Quantity available
     */
    public function __construct(
        string $productName,
        int $requested,
        int $available
    ) {
        parent::__construct(
            ErrorMessages::insufficientStock($productName, $available),
            [
                'product' => $productName,
                'requested' => $requested,
                'available' => $available,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'INSUFFICIENT_STOCK';
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

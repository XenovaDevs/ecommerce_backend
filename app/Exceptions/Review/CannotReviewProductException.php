<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use App\Exceptions\BaseException;

class CannotReviewProductException extends BaseException
{
    public function __construct(
        string $message = 'You must purchase this product before reviewing it',
        array $metadata = []
    ) {
        parent::__construct($message, $metadata);
    }

    public function getErrorCode(): string
    {
        return 'CANNOT_REVIEW_PRODUCT';
    }

    public function getHttpStatus(): int
    {
        return 403;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

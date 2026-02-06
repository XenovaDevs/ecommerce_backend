<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use App\Exceptions\BaseException;

class DuplicateReviewException extends BaseException
{
    public function __construct(
        string $message = 'You have already reviewed this product',
        array $metadata = []
    ) {
        parent::__construct($message, $metadata);
    }

    public function getErrorCode(): string
    {
        return 'DUPLICATE_REVIEW';
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

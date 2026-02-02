<?php

declare(strict_types=1);

namespace App\Services\Shipping\Exceptions;

/**
 * Exception thrown when Andreani API communication fails.
 */
class AndreaniApiException extends \RuntimeException
{
    private array $responseData;
    private int $httpStatus;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        array $responseData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->responseData = $responseData;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}

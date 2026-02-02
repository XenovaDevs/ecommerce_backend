<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when user is not authorized to perform an action.
 * @ai-flow Thrown by policies/middleware -> Caught by handler -> Returns 403
 */
class UnauthorizedException extends BaseException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? ErrorMessages::AUTH['UNAUTHORIZED']);
    }

    public function getErrorCode(): string
    {
        return 'UNAUTHORIZED';
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

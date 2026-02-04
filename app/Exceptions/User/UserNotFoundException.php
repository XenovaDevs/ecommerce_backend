<?php

declare(strict_types=1);

namespace App\Exceptions\User;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when a user is not found.
 */
class UserNotFoundException extends BaseException
{
    public function __construct()
    {
        parent::__construct('User not found');
    }

    public function getErrorCode(): string
    {
        return 'USER_NOT_FOUND';
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

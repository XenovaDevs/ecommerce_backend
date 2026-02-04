<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseException;

/**
 * @ai-context Exception thrown when password reset token is invalid or expired.
 */
class InvalidPasswordResetTokenException extends BaseException
{
    public function __construct()
    {
        parent::__construct('The password reset token is invalid or has expired');
    }

    public function getErrorCode(): string
    {
        return 'INVALID_PASSWORD_RESET_TOKEN';
    }

    public function getHttpStatus(): int
    {
        return 400;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

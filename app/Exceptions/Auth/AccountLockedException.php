<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

/**
 * @ai-context Exception thrown when account is locked due to too many failed attempts.
 * @ai-security Should inform user about lockout but not give too much detail
 * @ai-flow Thrown by AuthService -> Caught by handler -> Returns 423 Locked
 */
class AccountLockedException extends BaseException
{
    public function __construct(?int $minutesRemaining = null)
    {
        $metadata = [];

        if ($minutesRemaining !== null) {
            $metadata['retry_after_minutes'] = $minutesRemaining;
        }

        parent::__construct(ErrorMessages::AUTH['ACCOUNT_LOCKED'], $metadata);
    }

    public function getErrorCode(): string
    {
        return 'ACCOUNT_LOCKED';
    }

    public function getHttpStatus(): int
    {
        return 423;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

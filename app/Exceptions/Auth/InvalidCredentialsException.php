<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

class InvalidCredentialsException extends BaseException
{
    public function __construct()
    {
        parent::__construct(ErrorMessages::AUTH['INVALID_CREDENTIALS']);
    }

    public function getErrorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function getHttpStatus(): int
    {
        return 401;
    }

    public function isOperational(): bool
    {
        return true;
    }
}

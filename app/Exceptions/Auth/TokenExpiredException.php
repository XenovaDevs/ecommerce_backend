<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseException;
use App\Messages\ErrorMessages;

class TokenExpiredException extends BaseException
{
    public function __construct()
    {
        parent::__construct(ErrorMessages::AUTH['TOKEN_EXPIRED']);
    }

    public function getErrorCode(): string
    {
        return 'TOKEN_EXPIRED';
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

<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/**
 * @ai-context OrderChannel authorizes private order channels for users.
 */
class OrderChannel
{
    public function join(User $user, int $userId): bool
    {
        return $user->id === $userId || $user->isAdmin();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * @ai-context OrderPolicy handles authorization for order operations.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        // Staff can view all orders, customers can view their own
        return $user->tokenCan('orders.view-all') || $user->tokenCan('orders.view-own');
    }

    public function view(User $user, Order $order): bool
    {
        // Staff can view all orders
        if ($user->tokenCan('orders.view-all')) {
            return true;
        }

        // Customers can only view their own orders
        return $user->tokenCan('orders.view-own') && $user->id === $order->user_id;
    }

    public function create(User $user): bool
    {
        return $user->tokenCan('orders.create');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->tokenCan('orders.update-status');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->tokenCan('orders.update-status');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->tokenCan('orders.delete');
    }

    public function cancel(User $user, Order $order): bool
    {
        if (!$order->canBeCancelled()) {
            return false;
        }

        // Staff can cancel any order
        if ($user->tokenCan('orders.cancel')) {
            return true;
        }

        // Customers can cancel their own orders
        return $user->tokenCan('orders.view-own') && $user->id === $order->user_id;
    }
}

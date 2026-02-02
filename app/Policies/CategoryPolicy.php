<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * @ai-context CategoryPolicy handles authorization for category operations.
 */
class CategoryPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Category $category): bool
    {
        // Active categories are public
        if ($category->is_active) {
            return true;
        }

        // Staff can view inactive categories
        return $user && $user->tokenCan('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->tokenCan('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->tokenCan('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->tokenCan('categories.delete');
    }
}

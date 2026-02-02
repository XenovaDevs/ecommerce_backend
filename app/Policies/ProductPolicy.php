<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * @ai-context ProductPolicy handles authorization for product operations.
 */
class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        // Active products are public
        if ($product->is_active) {
            return true;
        }

        // Staff can view inactive products
        return $user && $user->tokenCan('products.view');
    }

    public function create(User $user): bool
    {
        return $user->tokenCan('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->tokenCan('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->tokenCan('products.delete');
    }

    public function manageImages(User $user, Product $product): bool
    {
        return $user->tokenCan('products.manage-images');
    }
}

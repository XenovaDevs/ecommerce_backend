<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Domain\Enums\UserRole;

/**
 * @ai-context Centralized role-based permissions matrix.
 *             Defines granular abilities for each user role.
 */
final class RolePermissions
{
    /**
     * Role-to-abilities mapping.
     *
     * @var array<string, array<string>>
     */
    public const ROLE_ABILITIES = [
        'super_admin' => ['*'],

        'admin' => [
            'dashboard.view',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.manage-images',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'orders.view-all', 'orders.update-status', 'orders.cancel', 'orders.delete', 'orders.create-shipment',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'reports.view-sales', 'reports.view-products', 'reports.view-customers',
            'settings.view', 'settings.update',
            'contacts.view', 'contacts.reply', 'contacts.update-status',
            'reviews.view', 'reviews.approve', 'reviews.reject', 'reviews.delete',
        ],

        'manager' => [
            'dashboard.view',
            'products.view', 'products.update', 'products.manage-images',
            'categories.view',
            'orders.view-all', 'orders.update-status',
            'customers.view',
            'reports.view-sales', 'reports.view-products',
            'contacts.view',
            'reviews.view', 'reviews.approve', 'reviews.reject', 'reviews.delete',
        ],

        'support' => [
            'dashboard.view',
            'products.view', 'categories.view',
            'orders.view-all',
            'customers.view',
            'reviews.view', 'reviews.reject',
        ],

        'customer' => [
            'profile.view', 'profile.update',
            'orders.view-own', 'orders.create', 'orders.cancel',
            'cart.manage', 'wishlist.manage', 'addresses.manage',
        ],
    ];

    /**
     * Get abilities for a specific role.
     *
     * @param UserRole $role
     * @return array<string>
     */
    public static function getAbilitiesForRole(UserRole $role): array
    {
        return self::ROLE_ABILITIES[$role->value] ?? [];
    }

    /**
     * Check if a role has a specific ability.
     *
     * @param UserRole $role
     * @param string $ability
     * @return bool
     */
    public static function hasAbility(UserRole $role, string $ability): bool
    {
        $abilities = self::getAbilitiesForRole($role);

        // Super admin has all abilities
        if (in_array('*', $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }

    /**
     * Get all defined abilities across all roles.
     *
     * @return array<string>
     */
    public static function getAllAbilities(): array
    {
        $abilities = [];

        foreach (self::ROLE_ABILITIES as $roleAbilities) {
            if (!in_array('*', $roleAbilities, true)) {
                $abilities = array_merge($abilities, $roleAbilities);
            }
        }

        return array_unique($abilities);
    }

    /**
     * Get roles that have a specific ability.
     *
     * @param string $ability
     * @return array<string>
     */
    public static function getRolesWithAbility(string $ability): array
    {
        $roles = [];

        foreach (self::ROLE_ABILITIES as $role => $abilities) {
            if (in_array('*', $abilities, true) || in_array($ability, $abilities, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}

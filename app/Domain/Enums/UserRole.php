<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * @ai-context User roles enum for authorization.
 *             Defines the available roles in the system.
 */
enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case SUPPORT = 'support';
    case CUSTOMER = 'customer';

    /**
     * Get human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::ADMIN => 'Administrator',
            self::MANAGER => 'Manager',
            self::SUPPORT => 'Support',
            self::CUSTOMER => 'Customer',
        };
    }

    /**
     * Check if the role is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Check if the role is admin.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if the role is staff (any administrative role).
     */
    public function isStaff(): bool
    {
        return in_array($this, [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::MANAGER,
            self::SUPPORT,
        ], true);
    }

    /**
     * Get all role values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

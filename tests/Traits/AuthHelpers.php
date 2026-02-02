<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Domain\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait AuthHelpers
{
    protected function createUser(UserRole $role = UserRole::CUSTOMER, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    protected function actingAsCustomer(array $attributes = []): User
    {
        $user = $this->createUser(UserRole::CUSTOMER, $attributes);
        Sanctum::actingAs($user, $this->getAbilitiesForRole(UserRole::CUSTOMER));
        return $user;
    }

    protected function actingAsSupport(array $attributes = []): User
    {
        $user = $this->createUser(UserRole::SUPPORT, $attributes);
        Sanctum::actingAs($user, $this->getAbilitiesForRole(UserRole::SUPPORT));
        return $user;
    }

    protected function actingAsManager(array $attributes = []): User
    {
        $user = $this->createUser(UserRole::MANAGER, $attributes);
        Sanctum::actingAs($user, $this->getAbilitiesForRole(UserRole::MANAGER));
        return $user;
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = $this->createUser(UserRole::ADMIN, $attributes);
        Sanctum::actingAs($user, $this->getAbilitiesForRole(UserRole::ADMIN));
        return $user;
    }

    protected function actingAsSuperAdmin(array $attributes = []): User
    {
        $user = $this->createUser(UserRole::SUPER_ADMIN, $attributes);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function getAbilitiesForRole(UserRole $role): array
    {
        return \App\Support\Authorization\RolePermissions::getAbilitiesForRole($role);
    }
}

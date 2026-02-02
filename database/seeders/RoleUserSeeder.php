<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * @ai-context Seeder for creating test users with different roles.
 *             Creates one user for each role for testing purposes.
 */
class RoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Admin
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Manager
        User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'role' => UserRole::MANAGER->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Support
        User::firstOrCreate(
            ['email' => 'support@example.com'],
            [
                'name' => 'Support User',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPPORT->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Customer
        User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('password'),
                'role' => UserRole::CUSTOMER->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Created test users for all roles (password: password)');
    }
}

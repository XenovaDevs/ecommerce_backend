<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * @ai-context Interface for setting repository.
 *             Defines the contract for settings data access.
 */
interface SettingRepositoryInterface
{
    /**
     * Get all settings.
     */
    public function getAll(): Collection;

    /**
     * Get public settings only.
     */
    public function getPublic(): Collection;

    /**
     * Get settings by group.
     */
    public function getByGroup(string $group): Collection;

    /**
     * Get a setting by key.
     */
    public function findByKey(string $key): ?Setting;

    /**
     * Get a setting value with optional default.
     */
    public function getValue(string $key, mixed $default = null): mixed;

    /**
     * Update a setting value.
     */
    public function setValue(string $key, mixed $value): void;

    /**
     * Update multiple settings at once.
     *
     * @param array<string, mixed> $settings Key-value pairs
     */
    public function setMultiple(array $settings): void;

    /**
     * Create a new setting.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Setting;
}

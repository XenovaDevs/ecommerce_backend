<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * @ai-context Service for managing application settings.
 *             Provides business logic for settings CRUD operations.
 */
class SettingService
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository
    ) {}

    /**
     * Get all settings.
     */
    public function getAllSettings(): Collection
    {
        return $this->repository->getAll();
    }

    /**
     * Get public settings for storefront.
     */
    public function getPublicSettings(): Collection
    {
        return $this->repository->getPublic();
    }

    /**
     * Get settings by group.
     */
    public function getSettingsByGroup(string $group): Collection
    {
        return $this->repository->getByGroup($group);
    }

    /**
     * Get a specific setting by key.
     *
     * @throws EntityNotFoundException
     */
    public function getSetting(string $key): Setting
    {
        $setting = $this->repository->findByKey($key);

        if (!$setting) {
            throw new EntityNotFoundException('Setting', $key);
        }

        return $setting;
    }

    /**
     * Get a setting value with optional default.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        return $this->repository->getValue($key, $default);
    }

    /**
     * Update a setting value.
     *
     * @throws EntityNotFoundException
     */
    public function updateSetting(string $key, mixed $value): void
    {
        $setting = $this->repository->findByKey($key);

        if (!$setting) {
            throw new EntityNotFoundException('Setting', $key);
        }

        $this->repository->setValue($key, $value);
    }

    /**
     * Update multiple settings.
     *
     * @param array<string, mixed> $settings
     */
    public function updateMultiple(array $settings): void
    {
        $this->repository->setMultiple($settings);
    }

    /**
     * Get store configuration (common settings).
     *
     * @return array<string, mixed>
     */
    public function getStoreConfig(): array
    {
        return [
            'store_name' => $this->getValue('store_name', 'My Store'),
            'store_logo' => $this->getValue('store_logo'),
            'store_email' => $this->getValue('store_email'),
            'store_phone' => $this->getValue('store_phone'),
            'store_address' => $this->getValue('store_address'),
            'currency' => $this->getValue('currency', 'ARS'),
            'currency_symbol' => $this->getValue('currency_symbol', '$'),
            'tax_rate' => $this->getValue('tax_rate', 21),
            'timezone' => $this->getValue('timezone', 'America/Argentina/Buenos_Aires'),
        ];
    }
}

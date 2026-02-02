<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Models\Setting;
use App\Support\Constants\CacheKeys;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @ai-context Eloquent implementation of SettingRepositoryInterface.
 *             Includes caching for performance.
 */
class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        private readonly Setting $model
    ) {}

    public function getAll(): Collection
    {
        return Cache::tags([CacheKeys::TAG_SETTINGS])
            ->remember(
                CacheKeys::SETTINGS_ALL,
                CacheKeys::TTL_SETTINGS,
                fn () => $this->model->all()
            );
    }

    public function getPublic(): Collection
    {
        return Cache::tags([CacheKeys::TAG_SETTINGS])
            ->remember(
                CacheKeys::SETTINGS_PUBLIC,
                CacheKeys::TTL_SETTINGS,
                fn () => $this->model->public()->get()
            );
    }

    public function getByGroup(string $group): Collection
    {
        return Cache::tags([CacheKeys::TAG_SETTINGS])
            ->remember(
                CacheKeys::settingsByGroup($group),
                CacheKeys::TTL_SETTINGS,
                fn () => $this->model->group($group)->get()
            );
    }

    public function findByKey(string $key): ?Setting
    {
        return Cache::tags([CacheKeys::TAG_SETTINGS])
            ->remember(
                CacheKeys::setting($key),
                CacheKeys::TTL_SETTINGS,
                fn () => $this->model->where('key', $key)->first()
            );
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $setting = $this->findByKey($key);
        return $setting?->typed_value ?? $default;
    }

    public function setValue(string $key, mixed $value): void
    {
        $setting = $this->model->where('key', $key)->first();

        if ($setting) {
            $setting->setTypedValue($value);
            $setting->save();
            $this->clearCache();
        }
    }

    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $setting = $this->model->where('key', $key)->first();
            if ($setting) {
                $setting->setTypedValue($value);
                $setting->save();
            }
        }

        $this->clearCache();
    }

    public function create(array $data): Setting
    {
        $setting = $this->model->create($data);
        $this->clearCache();
        return $setting;
    }

    /**
     * Clear settings cache.
     */
    private function clearCache(): void
    {
        Cache::tags([CacheKeys::TAG_SETTINGS])->flush();
    }
}

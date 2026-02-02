<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @ai-context Setting model for application configuration.
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_public',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'array', 'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember(
            "setting:{$key}",
            3600,
            fn () => self::where('key', $key)->first()
        );

        return $setting?->typed_value ?? $default;
    }

    public static function set(string $key, mixed $value, ?string $type = null): void
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => self::serializeValue($value, $type ?? $setting->type)]);
        }

        Cache::forget("setting:{$key}");
        Cache::forget('settings:public');
        Cache::forget('settings:all');
    }

    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->typed_value])
            ->toArray();
    }

    public static function getPublic(): array
    {
        return Cache::remember('settings:public', 3600, function () {
            return self::where('is_public', true)
                ->get()
                ->mapWithKeys(fn ($s) => [$s->key => $s->typed_value])
                ->toArray();
        });
    }

    public static function getAll(): array
    {
        return Cache::remember('settings:all', 3600, function () {
            return self::all()
                ->mapWithKeys(fn ($s) => [$s->key => $s->typed_value])
                ->toArray();
        });
    }

    private static function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'array', 'json' => json_encode($value),
            default => (string) $value,
        };
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}

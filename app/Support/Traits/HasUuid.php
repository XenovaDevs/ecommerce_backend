<?php

declare(strict_types=1);

namespace App\Support\Traits;

use Illuminate\Support\Str;

/**
 * @ai-context Trait for Eloquent models that use UUID as primary or secondary identifier.
 *             Auto-generates UUID on model creation.
 * @ai-flow
 *   1. Model is being created -> 2. Boot method hooks into creating event
 *   3. UUID is auto-generated if not set
 */
trait HasUuid
{
    /**
     * Boot the trait.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $uuidColumn = $model->getUuidColumn();

            if (empty($model->{$uuidColumn})) {
                $model->{$uuidColumn} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the column name for the UUID.
     * Override this method to use a different column.
     */
    public function getUuidColumn(): string
    {
        return property_exists($this, 'uuidColumn') ? $this->uuidColumn : 'uuid';
    }

    /**
     * Find a model by its UUID.
     *
     * @param string $uuid
     * @return static|null
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where((new static())->getUuidColumn(), $uuid)->first();
    }

    /**
     * Find a model by its UUID or fail.
     *
     * @param string $uuid
     * @return static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where((new static())->getUuidColumn(), $uuid)->firstOrFail();
    }

    /**
     * Scope a query to find by UUID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $uuid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereUuid($query, string $uuid)
    {
        return $query->where($this->getUuidColumn(), $uuid);
    }
}

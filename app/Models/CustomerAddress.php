<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context CustomerAddress model for saved customer addresses.
 */
class CustomerAddress extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'label',
        'type',
        'name',
        'phone',
        'address',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);
        return implode(', ', $parts);
    }

    public function setAsDefault(): void
    {
        self::where('user_id', $this->user_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeShipping($query)
    {
        return $query->where('type', 'shipping');
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing');
    }
}

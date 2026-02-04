<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @ai-context Coupon model for discount codes.
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'minimum_amount' => 'float',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usage(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function isValidForAmount(float $amount): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->minimum_amount && $amount < $this->minimum_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'fixed') {
            return min($this->value, $amount);
        }

        return round($amount * ($this->value / 100), 2);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeStarted($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        });
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->notExpired()
            ->started()
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereRaw('used_count < max_uses');
            });
    }
}

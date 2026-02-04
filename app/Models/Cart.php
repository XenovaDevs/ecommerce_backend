<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @ai-context Cart model for shopping cart functionality.
 */
class Cart extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'session_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'cart_coupons');
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->total);
    }

    public function getDiscountAttribute(): float
    {
        $subtotal = $this->subtotal;
        $totalDiscount = 0.0;

        foreach ($this->coupons as $coupon) {
            if ($coupon->isValidForAmount($subtotal - $totalDiscount)) {
                $totalDiscount += $coupon->calculateDiscount($subtotal - $totalDiscount);
            }
        }

        return $totalDiscount;
    }

    public function getTaxAttribute(): float
    {
        $taxEnabled = Setting::get('tax_enabled', false);
        $taxIncluded = Setting::get('tax_included_in_prices', true);

        if (!$taxEnabled || $taxIncluded) {
            return 0.0;
        }

        $taxRate = Setting::get('tax_rate', 21);
        return round($this->subtotal * ($taxRate / 100), 2);
    }

    public function getTotalAttribute(): float
    {
        return $this->subtotal - $this->discount + $this->tax;
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getIsEmptyAttribute(): bool
    {
        return $this->items->isEmpty();
    }

    public function clear(): void
    {
        $this->items()->delete();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}

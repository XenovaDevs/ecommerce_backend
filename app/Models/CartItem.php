<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context CartItem model for items in a shopping cart.
 */
class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'price_at_addition',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_at_addition' => 'float',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getCurrentPriceAttribute(): float
    {
        if ($this->variant) {
            return $this->variant->current_price;
        }
        return $this->product->current_price;
    }

    public function getTotalAttribute(): float
    {
        return $this->current_price * $this->quantity;
    }

    public function getAvailableStockAttribute(): int
    {
        if (!$this->product->track_stock) {
            return PHP_INT_MAX;
        }
        if ($this->variant) {
            return $this->variant->stock;
        }
        return $this->product->stock;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->available_stock >= $this->quantity;
    }
}

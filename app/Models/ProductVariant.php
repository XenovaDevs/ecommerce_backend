<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context ProductVariant model for product variations (size, color, etc.).
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $name
 * @property array $attributes
 * @property float|null $price
 * @property int $stock
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'price',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'float',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the current price (variant price or product price).
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->price ?? $this->product->current_price;
    }

    /**
     * Check if variant is in stock.
     */
    public function getInStockAttribute(): bool
    {
        if (!$this->product->track_stock) {
            return true;
        }
        return $this->stock > 0;
    }

    /**
     * Decrease stock.
     */
    public function decreaseStock(int $quantity): void
    {
        if ($this->product->track_stock) {
            $this->decrement('stock', $quantity);
        }
    }

    /**
     * Increase stock.
     */
    public function increaseStock(int $quantity): void
    {
        if ($this->product->track_stock) {
            $this->increment('stock', $quantity);
        }
    }

    /**
     * Get variant attribute value by name.
     */
    public function getVariantAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Get formatted attributes string.
     */
    public function getFormattedAttributesAttribute(): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        return collect($this->attributes)
            ->map(fn ($value, $key) => "$key: $value")
            ->implode(', ');
    }
}

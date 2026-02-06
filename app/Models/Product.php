<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @ai-context Product model for the ecommerce catalog.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property float $price
 * @property float|null $sale_price
 * @property float|null $compare_at_price
 * @property float|null $cost_price
 * @property string $sku
 * @property string|null $barcode
 * @property int $stock
 * @property int $low_stock_threshold
 * @property int|null $category_id
 * @property bool $is_featured
 * @property bool $is_active
 * @property bool $track_stock
 * @property float $weight
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'sale_price',
        'compare_at_price',
        'cost_price',
        'sku',
        'barcode',
        'stock',
        'low_stock_threshold',
        'category_id',
        'is_featured',
        'is_active',
        'track_stock',
        'weight',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'sale_price' => 'float',
            'compare_at_price' => 'float',
            'cost_price' => 'float',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'weight' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(Str::random(8));
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true);
    }

    public function getCurrentPriceAttribute(): float
    {
        if ($this->sale_price && $this->sale_price < $this->price) {
            return $this->sale_price;
        }
        return $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->is_on_sale) {
            return null;
        }
        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    public function getInStockAttribute(): bool
    {
        if (!$this->track_stock) {
            return true;
        }
        if ($this->variants()->exists()) {
            return $this->variants()->where('is_active', true)->sum('stock') > 0;
        }
        return $this->stock > 0;
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->variants()->exists()) {
            return (int) $this->variants()->where('is_active', true)->sum('stock');
        }
        return $this->stock;
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) $this->approvedReviews()->avg('rating') ?? 0.0;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    public function decreaseStock(int $quantity, ?int $variantId = null): void
    {
        if (!$this->track_stock) {
            return;
        }

        if ($variantId) {
            $this->variants()->where('id', $variantId)->decrement('stock', $quantity);
        } else {
            $this->decrement('stock', $quantity);
        }
    }

    public function increaseStock(int $quantity, ?int $variantId = null): void
    {
        if (!$this->track_stock) {
            return;
        }

        if ($variantId) {
            $this->variants()->where('id', $variantId)->increment('stock', $quantity);
        } else {
            $this->increment('stock', $quantity);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_stock', false)
                ->orWhere('stock', '>', 0);
        });
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePriceRange($query, ?float $min, ?float $max)
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        return $query;
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }
}

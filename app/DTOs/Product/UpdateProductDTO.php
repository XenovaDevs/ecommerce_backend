<?php

declare(strict_types=1);

namespace App\DTOs\Product;

/**
 * @ai-context UpdateProductDTO for product update data transfer.
 */
readonly class UpdateProductDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $short_description = null,
        public ?float $price = null,
        public ?float $sale_price = null,
        public ?float $compare_at_price = null,
        public ?float $cost_price = null,
        public ?string $sku = null,
        public ?string $barcode = null,
        public ?int $stock = null,
        public ?int $low_stock_threshold = null,
        public ?int $category_id = null,
        public ?bool $is_featured = null,
        public ?bool $is_active = null,
        public ?bool $track_stock = null,
        public ?float $weight = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'compare_at_price' => $this->compare_at_price,
            'cost_price' => $this->cost_price,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'stock' => $this->stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'category_id' => $this->category_id,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'track_stock' => $this->track_stock,
            'weight' => $this->weight,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ], fn ($value) => $value !== null);
    }
}

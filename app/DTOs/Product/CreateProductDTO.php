<?php

declare(strict_types=1);

namespace App\DTOs\Product;

/**
 * @ai-context CreateProductDTO for product creation data transfer.
 */
readonly class CreateProductDTO
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $short_description = null,
        public float $price = 0,
        public ?float $sale_price = null,
        public ?string $sku = null,
        public ?int $stock = 0,
        public ?int $category_id = null,
        public bool $is_featured = false,
        public bool $is_active = true,
        public bool $track_stock = true,
        public float $weight = 0,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'category_id' => $this->category_id,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'track_stock' => $this->track_stock,
            'weight' => $this->weight,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }
}

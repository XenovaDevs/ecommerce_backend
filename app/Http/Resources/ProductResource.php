<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @ai-context API Resource for Product model.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'compare_at_price' => $this->compare_at_price,
            'cost_price' => $this->cost_price,
            'current_price' => $this->current_price,
            'is_on_sale' => $this->is_on_sale,
            'discount_percentage' => $this->discount_percentage,
            'stock' => $this->when($this->track_stock, $this->total_stock),
            'low_stock_threshold' => $this->low_stock_threshold,
            'in_stock' => $this->in_stock,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'track_stock' => $this->track_stock,
            'weight' => $this->weight,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'meta' => [
                'title' => $this->meta_title ?? $this->name,
                'description' => $this->meta_description ?? $this->short_description,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

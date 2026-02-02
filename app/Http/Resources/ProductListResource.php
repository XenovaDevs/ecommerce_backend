<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @ai-context Lightweight API Resource for product listings.
 *             Used for product grids and lists.
 *
 * @property \App\Models\Product $resource
 */
class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'short_description' => $this->resource->short_description,
            'price' => $this->resource->price,
            'sale_price' => $this->resource->sale_price,
            'current_price' => $this->resource->current_price,
            'is_on_sale' => $this->resource->is_on_sale,
            'discount_percentage' => $this->resource->discount_percentage,
            'in_stock' => $this->resource->in_stock,
            'is_featured' => $this->resource->is_featured,
            'image' => $this->when(
                $this->resource->relationLoaded('primaryImage') && $this->resource->primaryImage,
                fn () => [
                    'url' => $this->resource->primaryImage->url,
                    'alt' => $this->resource->primaryImage->alt,
                ]
            ),
            'category' => $this->when(
                $this->resource->relationLoaded('category') && $this->resource->category,
                fn () => [
                    'id' => $this->resource->category->id,
                    'name' => $this->resource->category->name,
                    'slug' => $this->resource->category->slug,
                ]
            ),
        ];
    }
}

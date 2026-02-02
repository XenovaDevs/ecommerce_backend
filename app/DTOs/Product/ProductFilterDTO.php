<?php

declare(strict_types=1);

namespace App\DTOs\Product;

use Illuminate\Http\Request;

/**
 * @ai-context DTO for product filtering and search parameters.
 */
final readonly class ProductFilterDTO
{
    public function __construct(
        public ?int $categoryId = null,
        public ?string $search = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?bool $inStock = null,
        public ?bool $featured = null,
        public string $sortBy = 'created_at',
        public string $sortDirection = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // Support both 'category' and 'category_id' parameter names
        $categoryId = $request->filled('category')
            ? (int) $request->input('category')
            : ($request->filled('category_id') ? (int) $request->input('category_id') : null);

        return new self(
            categoryId: $categoryId,
            search: $request->input('search'),
            minPrice: $request->filled('min_price') ? (float) $request->input('min_price') : null,
            maxPrice: $request->filled('max_price') ? (float) $request->input('max_price') : null,
            inStock: $request->has('in_stock') ? $request->boolean('in_stock') : null,
            featured: $request->has('featured') ? $request->boolean('featured') : null,
            sortBy: $request->input('sort_by', 'created_at'),
            sortDirection: $request->input('sort_direction', 'desc'),
            perPage: (int) $request->input('per_page', 15),
        );
    }
}

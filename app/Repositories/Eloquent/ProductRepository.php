<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTOs\Product\ProductFilterDTO;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @ai-context Eloquent implementation of ProductRepositoryInterface.
 */
class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly Product $model
    ) {}

    public function findById(int $id): ?Product
    {
        return $this->model
            ->with(['category', 'images', 'variants'])
            ->find($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->model
            ->with(['category', 'images', 'activeVariants'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function paginate(ProductFilterDTO $filters): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['category', 'primaryImage'])
            ->active();

        if ($filters->categoryId) {
            $query->byCategory($filters->categoryId);
        }

        if ($filters->search) {
            $query->search($filters->search);
        }

        if ($filters->minPrice !== null || $filters->maxPrice !== null) {
            $query->priceRange($filters->minPrice, $filters->maxPrice);
        }

        if ($filters->inStock === true) {
            $query->inStock();
        }

        if ($filters->featured === true) {
            $query->featured();
        }

        $allowedSorts = ['created_at', 'price', 'name'];
        $sortBy = in_array($filters->sortBy, $allowedSorts) ? $filters->sortBy : 'created_at';
        $sortDirection = in_array(strtolower($filters->sortDirection), ['asc', 'desc']) ? $filters->sortDirection : 'desc';

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($filters->perPage);
    }

    public function getFeatured(int $limit = 8): Collection
    {
        return $this->model
            ->with(['primaryImage'])
            ->active()
            ->featured()
            ->inStock()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}

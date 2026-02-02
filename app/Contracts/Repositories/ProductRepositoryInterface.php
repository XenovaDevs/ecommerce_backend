<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\Product\ProductFilterDTO;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @ai-context Interface for Product repository operations.
 */
interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    public function paginate(ProductFilterDTO $filters): LengthAwarePaginator;

    public function getFeatured(int $limit = 8): Collection;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): void;
}

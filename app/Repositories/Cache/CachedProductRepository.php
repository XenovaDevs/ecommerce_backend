<?php

declare(strict_types=1);

namespace App\Repositories\Cache;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use App\Support\Constants\CacheKeys;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @ai-context Cache decorator for ProductRepository.
 *             Adds caching layer for improved performance.
 */
class CachedProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache paginated results with filters - too many variations
        return $this->repository->paginate($filters, $perPage);
    }

    public function getFeatured(int $limit = 8): Collection
    {
        return Cache::tags([CacheKeys::TAG_PRODUCTS])
            ->remember(
                CacheKeys::PRODUCTS_FEATURED . ':' . $limit,
                CacheKeys::TTL_PRODUCTS,
                fn () => $this->repository->getFeatured($limit)
            );
    }

    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache paginated results
        return $this->repository->getByCategory($categoryId, $perPage);
    }

    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache search results
        return $this->repository->search($query, $perPage);
    }

    public function findById(int $id): ?Product
    {
        return Cache::tags([CacheKeys::TAG_PRODUCTS])
            ->remember(
                CacheKeys::product($id),
                CacheKeys::TTL_PRODUCTS,
                fn () => $this->repository->findById($id)
            );
    }

    public function findBySlug(string $slug): ?Product
    {
        return Cache::tags([CacheKeys::TAG_PRODUCTS])
            ->remember(
                CacheKeys::productBySlug($slug),
                CacheKeys::TTL_PRODUCTS,
                fn () => $this->repository->findBySlug($slug)
            );
    }

    public function findBySku(string $sku): ?Product
    {
        return Cache::tags([CacheKeys::TAG_PRODUCTS])
            ->remember(
                CacheKeys::PREFIX_PRODUCT . 'sku:' . $sku,
                CacheKeys::TTL_PRODUCTS,
                fn () => $this->repository->findBySku($sku)
            );
    }

    public function create(array $data): Product
    {
        $product = $this->repository->create($data);
        $this->clearCache();
        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $product = $this->repository->update($product, $data);
        $this->clearCache();
        return $product;
    }

    public function delete(Product $product): void
    {
        $this->repository->delete($product);
        $this->clearCache();
    }

    public function getLowStock(int $threshold = 5): Collection
    {
        return $this->repository->getLowStock($threshold);
    }

    public function getRelated(Product $product, int $limit = 4): Collection
    {
        return Cache::tags([CacheKeys::TAG_PRODUCTS])
            ->remember(
                CacheKeys::product($product->id) . ':related:' . $limit,
                CacheKeys::TTL_SHORT,
                fn () => $this->repository->getRelated($product, $limit)
            );
    }

    private function clearCache(): void
    {
        Cache::tags([CacheKeys::TAG_PRODUCTS])->flush();
    }
}

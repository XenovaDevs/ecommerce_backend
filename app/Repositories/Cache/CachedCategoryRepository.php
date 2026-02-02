<?php

declare(strict_types=1);

namespace App\Repositories\Cache;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use App\Support\Constants\CacheKeys;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @ai-context Cache decorator for CategoryRepository.
 *             Adds caching layer for improved performance.
 */
class CachedCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository
    ) {}

    public function getAll(): Collection
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::CATEGORIES_ALL,
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->getAll()
            );
    }

    public function getActive(): Collection
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::CATEGORIES_ALL . ':active',
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->getActive()
            );
    }

    public function getRootCategories(): Collection
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::CATEGORIES_ALL . ':root',
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->getRootCategories()
            );
    }

    public function getTree(): Collection
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::CATEGORIES_TREE,
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->getTree()
            );
    }

    public function findById(int $id): ?Category
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::category($id),
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->findById($id)
            );
    }

    public function findBySlug(string $slug): ?Category
    {
        return Cache::tags([CacheKeys::TAG_CATEGORIES])
            ->remember(
                CacheKeys::categoryBySlug($slug),
                CacheKeys::TTL_CATEGORIES,
                fn () => $this->repository->findBySlug($slug)
            );
    }

    public function create(array $data): Category
    {
        $category = $this->repository->create($data);
        $this->clearCache();
        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $category = $this->repository->update($category, $data);
        $this->clearCache();
        return $category;
    }

    public function delete(Category $category): void
    {
        $this->repository->delete($category);
        $this->clearCache();
    }

    public function reorder(array $positions): void
    {
        $this->repository->reorder($positions);
        $this->clearCache();
    }

    private function clearCache(): void
    {
        Cache::tags([CacheKeys::TAG_CATEGORIES])->flush();
    }
}

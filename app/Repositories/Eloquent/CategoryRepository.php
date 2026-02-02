<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly Category $model
    ) {}

    public function findById(int $id): ?Category
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->model->where('slug', $slug)->where('is_active', true)->first();
    }

    public function getActive(bool $rootOnly = false, bool $withChildren = true): Collection
    {
        $query = $this->model->active()->ordered();

        if ($rootOnly) {
            $query->root();
        }

        if ($withChildren) {
            $query->with(['children' => fn ($q) => $q->active()->ordered()]);
        }

        return $query->get();
    }

    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}

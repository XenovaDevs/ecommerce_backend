<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminCategoryController handles admin category management.
 */
class AdminCategoryController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly CategoryRepositoryInterface $repository
    ) {}

    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->withCount('products')
            ->orderBy('position')
            ->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->repository->create($validated);

        return $this->created(new CategoryResource($category));
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new EntityNotFoundException('Category', $id);
        }

        return $this->success(new CategoryResource($category->load(['parent', 'children'])));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new EntityNotFoundException('Category', $id);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:categories,slug,' . $id],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->repository->update($category, $validated);

        return $this->success(new CategoryResource($category));
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new EntityNotFoundException('Category', $id);
        }

        $this->repository->delete($category);

        return $this->noContent();
    }
}

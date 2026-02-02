<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context CategoryController handles public category API endpoints.
 */
class CategoryController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $includeChildren = $request->boolean('include_children', true);
        $rootOnly = $request->boolean('root_only', false);

        $categories = $this->categoryRepository->getActive($rootOnly, $includeChildren);

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(string $slug): JsonResponse
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            throw new EntityNotFoundException('Category', $slug);
        }

        return $this->success(new CategoryResource($category->load(['parent', 'children'])));
    }
}

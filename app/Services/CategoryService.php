<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Messages\ErrorMessages;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * @ai-context Service for managing categories.
 *             Provides business logic for category CRUD operations.
 */
class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository
    ) {}

    /**
     * Get all categories (for admin).
     */
    public function getAllCategories(): Collection
    {
        return $this->repository->getAll();
    }

    /**
     * Get active categories (for storefront).
     */
    public function getActiveCategories(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Get categories tree for navigation.
     */
    public function getCategoriesTree(): Collection
    {
        return $this->repository->getTree();
    }

    /**
     * Get category by ID.
     *
     * @throws EntityNotFoundException
     */
    public function getCategoryById(int $id): Category
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new EntityNotFoundException('Category', $id);
        }

        return $category;
    }

    /**
     * Get category by slug.
     *
     * @throws EntityNotFoundException
     */
    public function getCategoryBySlug(string $slug): Category
    {
        $category = $this->repository->findBySlug($slug);

        if (!$category) {
            throw new EntityNotFoundException('Category', $slug);
        }

        return $category;
    }

    /**
     * Create a new category.
     *
     * @param array<string, mixed> $data
     * @throws BusinessRuleException
     */
    public function createCategory(array $data): Category
    {
        // Validate parent exists if provided
        if (!empty($data['parent_id'])) {
            $parent = $this->repository->findById($data['parent_id']);
            if (!$parent) {
                throw new BusinessRuleException(
                    ErrorMessages::CATEGORY['NOT_FOUND'],
                    'PARENT_NOT_FOUND'
                );
            }
        }

        return $this->repository->create($data);
    }

    /**
     * Update a category.
     *
     * @param array<string, mixed> $data
     * @throws EntityNotFoundException
     * @throws BusinessRuleException
     */
    public function updateCategory(int $id, array $data): Category
    {
        $category = $this->getCategoryById($id);

        // Validate parent to prevent circular reference
        if (!empty($data['parent_id'])) {
            if ($data['parent_id'] === $id) {
                throw new BusinessRuleException(
                    ErrorMessages::CATEGORY['CIRCULAR_REFERENCE'],
                    'CIRCULAR_REFERENCE'
                );
            }

            // Check if the new parent is not a descendant
            $this->validateNotDescendant($category, $data['parent_id']);
        }

        return $this->repository->update($category, $data);
    }

    /**
     * Delete a category.
     *
     * @throws EntityNotFoundException
     * @throws BusinessRuleException
     */
    public function deleteCategory(int $id): void
    {
        $category = $this->getCategoryById($id);

        // Check for children
        if ($category->hasChildren()) {
            throw new BusinessRuleException(
                ErrorMessages::CATEGORY['HAS_CHILDREN'],
                'HAS_CHILDREN'
            );
        }

        // Check for products
        if ($category->products()->exists()) {
            throw new BusinessRuleException(
                ErrorMessages::CATEGORY['HAS_PRODUCTS'],
                'HAS_PRODUCTS'
            );
        }

        $this->repository->delete($category);
    }

    /**
     * Reorder categories.
     *
     * @param array<int, int> $positions
     */
    public function reorderCategories(array $positions): void
    {
        $this->repository->reorder($positions);
    }

    /**
     * Validate that the new parent is not a descendant of the category.
     *
     * @throws BusinessRuleException
     */
    private function validateNotDescendant(Category $category, int $newParentId): void
    {
        $descendants = $this->getDescendantIds($category);

        if (in_array($newParentId, $descendants)) {
            throw new BusinessRuleException(
                ErrorMessages::CATEGORY['CIRCULAR_REFERENCE'],
                'CIRCULAR_REFERENCE'
            );
        }
    }

    /**
     * Get all descendant IDs of a category.
     *
     * @return array<int>
     */
    private function getDescendantIds(Category $category): array
    {
        $ids = [];

        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }
}

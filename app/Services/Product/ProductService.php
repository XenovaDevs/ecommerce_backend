<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\ProductFilterDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @ai-context ProductService handles all product-related business logic.
 */
class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    public function list(ProductFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function featured(int $limit = 8): Collection
    {
        return $this->repository->getFeatured($limit);
    }

    public function findById(int $id): Product
    {
        $product = $this->repository->findById($id);

        if (!$product) {
            throw new EntityNotFoundException('Product', $id);
        }

        return $product;
    }

    public function findBySlug(string $slug): Product
    {
        $product = $this->repository->findBySlug($slug);

        if (!$product) {
            throw new EntityNotFoundException('Product', $slug);
        }

        return $product;
    }

    public function getRelated(int $productId, int $limit = 4): Collection
    {
        $product = $this->findById($productId);

        return Product::where('id', '!=', $productId)
            ->where('category_id', $product->category_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function create(CreateProductDTO $dto): Product
    {
        return $this->repository->create($dto->toArray());
    }

    public function update(int $id, UpdateProductDTO $dto): Product
    {
        $product = $this->findById($id);
        return $this->repository->update($product, $dto->toArray());
    }

    public function delete(int $id): void
    {
        $product = $this->findById($id);
        $this->repository->delete($product);
    }

    public function addImage(int $productId, string $url, bool $isPrimary = false): \App\Models\ProductImage
    {
        $product = $this->findById($productId);
        $position = $product->images()->count();

        if ($isPrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        return $product->images()->create([
            'url' => $url,
            'position' => $position,
            'is_primary' => $isPrimary,
        ]);
    }

    public function deleteImage(int $productId, int $imageId): void
    {
        $product = $this->findById($productId);
        $product->images()->where('id', $imageId)->delete();
    }

    public function addVariant(int $productId, array $data): void
    {
        $product = $this->findById($productId);
        $product->variants()->create($data);
    }

    public function updateStock(int $productId, int $quantity, ?int $variantId = null): void
    {
        $product = $this->findById($productId);

        if ($variantId) {
            $product->variants()->where('id', $variantId)->update(['stock' => $quantity]);
        } else {
            $product->update(['stock' => $quantity]);
        }
    }
}

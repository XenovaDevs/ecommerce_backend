<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\Product\ProductFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\Product\ProductService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminProductController handles admin product management.
 */
class AdminProductController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ProductFilterDTO::fromRequest($request);
        $products = $this->productService->list($filters);

        return $this->paginated(ProductResource::collection($products));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'gt:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $product = $this->productService->create(
            new \App\DTOs\Product\CreateProductDTO(
                name: $validated['name'],
                slug: $validated['slug'] ?? null,
                description: $validated['description'] ?? null,
                short_description: $validated['short_description'] ?? null,
                price: (float) ($validated['price'] ?? 0),
                sale_price: isset($validated['sale_price']) ? (float) $validated['sale_price'] : null,
                sku: $validated['sku'] ?? null,
                stock: isset($validated['stock']) ? (int) $validated['stock'] : 0,
                category_id: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                is_featured: (bool) ($validated['is_featured'] ?? false),
                is_active: (bool) ($validated['is_active'] ?? true),
                track_stock: (bool) ($validated['track_stock'] ?? true),
                weight: (float) ($validated['weight'] ?? 0),
                meta_title: $validated['meta_title'] ?? null,
                meta_description: $validated['meta_description'] ?? null,
            )
        );

        return $this->created(new ProductResource($product->load(['category', 'images'])));
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productService->findById($id);

        return $this->success(new ProductResource($product->load(['category', 'images', 'variants'])));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:products,slug,' . $id],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['sometimes', 'string', 'max:50', 'unique:products,sku,' . $id],
            'barcode' => ['nullable', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $product = $this->productService->update($id, new \App\DTOs\Product\UpdateProductDTO(...$validated));

        return $this->success(new ProductResource($product->load(['category', 'images', 'variants'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productService->delete($id);

        return $this->noContent();
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'], // 5MB max
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $url = $request->input('url');

        // Handle file upload if provided
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('products', 'public');
            $url = asset('storage/' . $path);
        }

        if (!$url) {
            return $this->error('Either url or image file is required', 'VALIDATION_ERROR', 422);
        }

        $image = $this->productService->addImage(
            $id,
            $url,
            $request->boolean('is_primary')
        );

        return $this->success(new \App\Http\Resources\ProductImageResource($image));
    }

    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $this->productService->deleteImage($id, $imageId);

        return $this->noContent();
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        foreach ($validated['ids'] as $id) {
            $this->productService->delete($id);
        }

        return $this->success(['message' => 'Products deleted successfully']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Product\ProductFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\Product\ProductService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context ProductController handles public product API endpoints.
 */
class ProductController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ProductFilterDTO::fromRequest($request);
        $products = $this->productService->list($filters);

        return $this->paginated(
            ProductResource::collection($products)
        );
    }

    public function featured(): JsonResponse
    {
        $products = $this->productService->featured();

        return $this->success(
            ProductResource::collection($products)
        );
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        return $this->success(
            new ProductResource($product)
        );
    }
}

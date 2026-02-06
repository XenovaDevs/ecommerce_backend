<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\Review\ReviewService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminReviewController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->role->isStaff()) {
            return $this->forbidden('Admin access required', 'FORBIDDEN');
        }

        $reviews = Review::with(['user', 'product'])
            ->when($request->query('product_id'), function ($query, $productId) {
                return $query->where('product_id', $productId);
            })
            ->when($request->query('is_approved'), function ($query, $isApproved) {
                return $query->where('is_approved', $isApproved === 'true');
            })
            ->latest()
            ->paginate($request->query('per_page', 15));

        return $this->paginated(ReviewResource::collection($reviews));
    }

    public function approve(Request $request, Review $review): JsonResponse
    {
        if (!$request->user()->role->isStaff()) {
            return $this->forbidden('Admin access required', 'FORBIDDEN');
        }

        $this->reviewService->approveReview($review);

        return $this->success(
            new ReviewResource($review->fresh()),
            'Review approved successfully'
        );
    }

    public function reject(Request $request, Review $review): JsonResponse
    {
        if (!$request->user()->role->isStaff()) {
            return $this->forbidden('Admin access required', 'FORBIDDEN');
        }

        $this->reviewService->rejectReview($review);

        return $this->success(
            new ReviewResource($review->fresh()),
            'Review rejected successfully'
        );
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        if (!$request->user()->role->isStaff()) {
            return $this->forbidden('Admin access required', 'FORBIDDEN');
        }

        $this->reviewService->deleteReview($review);

        return $this->noContent();
    }
}

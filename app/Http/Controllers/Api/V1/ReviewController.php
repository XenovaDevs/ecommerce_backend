<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Review\CannotReviewProductException;
use App\Exceptions\Review\DuplicateReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\CreateReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\Review\ReviewService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');

        $reviews = $this->reviewService->getProductReviews(
            (int) $productId,
            $request->query('per_page', 10)
        );

        return $this->paginated(ReviewResource::collection($reviews));
    }

    public function store(CreateReviewRequest $request): JsonResponse
    {
        try {
            $review = $this->reviewService->createReview(
                $request->user(),
                $request->validated()
            );

            return $this->created(
                new ReviewResource($review),
                'Review created successfully'
            );
        } catch (CannotReviewProductException | DuplicateReviewException $e) {
            return $this->error(
                $e->getMessage(),
                $e->getErrorCode(),
                $e->getHttpStatus()
            );
        }
    }

    public function show(Review $review): JsonResponse
    {
        return $this->success(new ReviewResource($review));
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id) {
            return $this->forbidden('You can only update your own reviews', 'FORBIDDEN');
        }

        $updatedReview = $this->reviewService->updateReview(
            $review,
            $request->validated()
        );

        return $this->success(
            new ReviewResource($updatedReview),
            'Review updated successfully'
        );
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id && !$request->user()->role->isStaff()) {
            return $this->forbidden('You can only delete your own reviews', 'FORBIDDEN');
        }

        $this->reviewService->deleteReview($review);

        return $this->noContent();
    }

    public function markHelpful(Request $request, Review $review): JsonResponse
    {
        $this->reviewService->markHelpful($review);

        return $this->success(
            new ReviewResource($review->fresh()),
            'Review marked as helpful'
        );
    }
}

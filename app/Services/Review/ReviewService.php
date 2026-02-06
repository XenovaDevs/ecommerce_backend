<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ReviewService
{
    public function canUserReviewProduct(User $user, Product $product): bool
    {
        return Order::where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->whereHas('items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->exists();
    }

    public function createReview(User $user, array $data): Review
    {
        $product = Product::findOrFail($data['product_id']);

        if (!$this->canUserReviewProduct($user, $product)) {
            throw new \App\Exceptions\Review\CannotReviewProductException();
        }

        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($existingReview) {
            throw new \App\Exceptions\Review\DuplicateReviewException();
        }

        $isVerified = $this->canUserReviewProduct($user, $product);

        $order = Order::where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->whereHas('items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->first();

        return Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order?->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_verified_purchase' => $isVerified,
            'is_approved' => true,
        ]);
    }

    public function updateReview(Review $review, array $data): Review
    {
        $review->update($data);
        return $review->fresh();
    }

    public function deleteReview(Review $review): void
    {
        $review->delete();
    }

    public function getProductReviews(int $productId, int $perPage = 10)
    {
        $query = Review::where('product_id', $productId)
            ->with('user')
            ->approved();

        return $query->latest()->paginate($perPage);
    }

    public function markHelpful(Review $review): void
    {
        $review->increment('helpful_count');
    }

    public function approveReview(Review $review): void
    {
        $review->update(['is_approved' => true]);
    }

    public function rejectReview(Review $review): void
    {
        $review->update(['is_approved' => false]);
    }
}

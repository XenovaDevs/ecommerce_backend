<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Exceptions\Coupon\CouponAlreadyAppliedException;
use App\Exceptions\Coupon\InvalidCouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @ai-context CouponService handles all coupon-related business logic.
 *             Follows Single Responsibility Principle by focusing solely on coupon operations.
 * @ai-flow
 *   1. Validate coupon code and eligibility
 *   2. Apply/remove coupon from cart
 *   3. Record usage when order is placed
 */
class CouponService
{
    /**
     * Validate a coupon code for a given amount.
     *
     * @param string $code The coupon code to validate
     * @param float $amount The cart subtotal to validate against
     * @return Coupon The validated coupon instance
     * @throws InvalidCouponException If coupon is invalid or cannot be applied
     */
    public function validateCoupon(string $code, float $amount): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            throw new InvalidCouponException(
                'Invalid coupon code',
                'COUPON_NOT_FOUND'
            );
        }

        if (!$coupon->is_active) {
            throw new InvalidCouponException(
                'This coupon is not active',
                'COUPON_INACTIVE'
            );
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new InvalidCouponException(
                'This coupon is not yet valid',
                'COUPON_NOT_STARTED',
                ['starts_at' => $coupon->starts_at->toIso8601String()]
            );
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new InvalidCouponException(
                'This coupon has expired',
                'COUPON_EXPIRED',
                ['expired_at' => $coupon->expires_at->toIso8601String()]
            );
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw new InvalidCouponException(
                'This coupon has reached its maximum usage limit',
                'COUPON_MAX_USES_REACHED',
                ['max_uses' => $coupon->max_uses]
            );
        }

        if ($coupon->minimum_amount && $amount < $coupon->minimum_amount) {
            throw new InvalidCouponException(
                sprintf(
                    'Cart subtotal must be at least $%.2f to use this coupon',
                    $coupon->minimum_amount
                ),
                'COUPON_MINIMUM_AMOUNT_NOT_REACHED',
                [
                    'minimum_amount' => $coupon->minimum_amount,
                    'current_amount' => $amount,
                ]
            );
        }

        return $coupon;
    }

    /**
     * Apply a coupon to a cart.
     *
     * @param Cart $cart The cart to apply the coupon to
     * @param string $code The coupon code to apply
     * @return void
     * @throws InvalidCouponException If coupon is invalid
     * @throws CouponAlreadyAppliedException If coupon is already applied to this cart
     */
    public function applyCouponToCart(Cart $cart, string $code): void
    {
        // Validate coupon is eligible for this cart amount
        $coupon = $this->validateCoupon($code, $cart->subtotal);

        // Check if coupon is already applied to this cart
        if ($cart->coupons()->where('coupon_id', $coupon->id)->exists()) {
            throw new CouponAlreadyAppliedException(
                'This coupon has already been applied to your cart'
            );
        }

        // Attach coupon to cart using the pivot table
        $cart->coupons()->attach($coupon->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Remove a coupon from a cart.
     *
     * @param Cart $cart The cart to remove the coupon from
     * @param int $couponId The ID of the coupon to remove
     * @return void
     * @throws InvalidCouponException If coupon is not applied to this cart
     */
    public function removeCouponFromCart(Cart $cart, int $couponId): void
    {
        // Verify the coupon is actually applied to this cart
        if (!$cart->coupons()->where('coupon_id', $couponId)->exists()) {
            throw new InvalidCouponException(
                'This coupon is not applied to your cart',
                'COUPON_NOT_APPLIED_TO_CART'
            );
        }

        // Detach the coupon from the cart
        $cart->coupons()->detach($couponId);
    }

    /**
     * Record coupon usage when an order is placed.
     * This method should be called after successful order creation.
     *
     * @param Coupon $coupon The coupon that was used
     * @param User $user The user who used the coupon
     * @param Order $order The order the coupon was applied to
     * @param float $discountAmount The actual discount amount applied
     * @return CouponUsage The created usage record
     */
    public function recordCouponUsage(
        Coupon $coupon,
        User $user,
        Order $order,
        float $discountAmount
    ): CouponUsage {
        return DB::transaction(function () use ($coupon, $user, $order, $discountAmount) {
            // Create usage record
            $usage = CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => $discountAmount,
                'used_at' => now(),
            ]);

            // Increment coupon usage count
            $coupon->incrementUsage();

            return $usage;
        });
    }

    /**
     * Calculate the total discount for all coupons applied to a cart.
     * This method applies coupons sequentially, reducing the amount for each subsequent coupon.
     *
     * @param Cart $cart The cart to calculate discounts for
     * @return float The total discount amount
     */
    public function calculateCartDiscount(Cart $cart): float
    {
        $subtotal = $cart->subtotal;
        $totalDiscount = 0.0;

        foreach ($cart->coupons as $coupon) {
            $remainingAmount = $subtotal - $totalDiscount;

            // Only apply coupon if it's still valid for the remaining amount
            if ($coupon->isValidForAmount($remainingAmount)) {
                $discount = $coupon->calculateDiscount($remainingAmount);
                $totalDiscount += $discount;
            }
        }

        return round($totalDiscount, 2);
    }
}

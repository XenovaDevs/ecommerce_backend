<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Exceptions\Coupon\InvalidCouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function applyCouponToCart(Cart $cart, string $code): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            throw new InvalidCouponException('Coupon not found');
        }

        if (!$coupon->isValid()) {
            throw new InvalidCouponException('Coupon is not valid');
        }

        if (!$coupon->isValidForAmount($cart->subtotal)) {
            throw new InvalidCouponException(
                "Minimum order amount is {$coupon->minimum_amount}"
            );
        }

        if ($cart->coupons()->where('coupon_id', $coupon->id)->exists()) {
            throw new InvalidCouponException('Coupon already applied');
        }

        $cart->coupons()->attach($coupon->id);

        return $coupon;
    }

    public function removeCouponFromCart(Cart $cart, int $couponId): void
    {
        $cart->coupons()->detach($couponId);
    }

    public function validateCartCoupons(Cart $cart): void
    {
        $cart->load('coupons');

        foreach ($cart->coupons as $coupon) {
            if (!$coupon->isValid()) {
                $cart->coupons()->detach($coupon->id);
                throw new InvalidCouponException(
                    "Coupon '{$coupon->code}' is no longer valid and has been removed"
                );
            }

            if (!$coupon->isValidForAmount($cart->subtotal)) {
                $cart->coupons()->detach($coupon->id);
                throw new InvalidCouponException(
                    "Cart no longer meets minimum amount for coupon '{$coupon->code}'"
                );
            }
        }
    }

    public function recordCouponUsage(Order $order, User $user, Coupon $coupon, float $discountAmount): CouponUsage
    {
        return DB::transaction(function () use ($order, $user, $coupon, $discountAmount) {
            $coupon->incrementUsage();

            return CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => $discountAmount,
                'used_at' => now(),
            ]);
        });
    }

    public function calculateCartDiscount(Cart $cart): float
    {
        $cart->load('coupons');

        $subtotal = $cart->subtotal;
        $totalDiscount = 0.0;

        foreach ($cart->coupons as $coupon) {
            if ($coupon->isValidForAmount($subtotal - $totalDiscount)) {
                $totalDiscount += $coupon->calculateDiscount($subtotal - $totalDiscount);
            }
        }

        return $totalDiscount;
    }
}

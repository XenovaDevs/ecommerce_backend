<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Resources\CartResource;
use App\Messages\SuccessMessages;
use App\Models\Cart;
use App\Models\Coupon;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context CouponController handles coupon application and removal in shopping carts.
 *             Follows thin controller pattern - delegates business logic to CouponService.
 * @ai-flow
 *   1. Controller receives request with validation
 *   2. Delegates to service layer for business logic
 *   3. Returns standardized JSON response
 */
class CouponController extends Controller
{
    use HasApiResponse;

    /**
     * Inject dependencies via constructor (Dependency Inversion Principle).
     */
    public function __construct(
        private readonly CouponService $couponService,
        private readonly CartService $cartService
    ) {}

    /**
     * Apply a coupon to the current cart.
     *
     * @route POST /api/v1/cart/coupons
     * @param ApplyCouponRequest $request Validated request containing coupon code
     * @return JsonResponse Cart with applied coupon and updated totals
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        // Get or create cart for current user/session
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        // Apply coupon through service layer
        $this->couponService->applyCouponToCart(
            $cart,
            $request->validated('code')
        );

        // Reload cart with relationships to calculate new totals
        $cart = $cart->fresh()->load(['items.product', 'items.variant', 'coupons']);

        // Calculate the discount amount for response
        $discount = $this->couponService->calculateCartDiscount($cart);

        return $this->success(
            [
                'cart' => new CartResource($cart),
                'discount' => $discount,
            ],
            SuccessMessages::COUPON['APPLIED']
        );
    }

    /**
     * Remove a coupon from the current cart.
     *
     * @route DELETE /api/v1/cart/coupons/{coupon}
     * @param Request $request Current request
     * @param Coupon $coupon The coupon to remove (route model binding)
     * @return JsonResponse Cart with coupon removed and updated totals
     */
    public function remove(Request $request, Coupon $coupon): JsonResponse
    {
        // Get or create cart for current user/session
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        // Remove coupon through service layer
        $this->couponService->removeCouponFromCart($cart, $coupon->id);

        // Reload cart with relationships to calculate new totals
        $cart = $cart->fresh()->load(['items.product', 'items.variant', 'coupons']);

        // Calculate the remaining discount amount
        $discount = $this->couponService->calculateCartDiscount($cart);

        return $this->success(
            [
                'cart' => new CartResource($cart),
                'discount' => $discount,
            ],
            SuccessMessages::COUPON['REMOVED']
        );
    }
}

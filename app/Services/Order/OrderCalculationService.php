<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\Setting;
use App\Services\Coupon\CouponService;

/**
 * @ai-context OrderCalculationService handles order total calculations.
 *             Follows Single Responsibility Principle by focusing solely on calculation logic.
 *             Delegates coupon discount calculation to CouponService.
 */
class OrderCalculationService
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * Calculate order totals including discounts, tax, and shipping.
     *
     * @param Cart $cart The shopping cart
     * @param float $shippingCost The shipping cost to apply
     * @return array<string, float> Array with keys: subtotal, discount, tax, shipping, total
     */
    public function calculate(Cart $cart, float $shippingCost = 0): array
    {
        $subtotal = $cart->subtotal;

        // Calculate coupon discounts first
        $discount = $this->couponService->calculateCartDiscount($cart);

        // Calculate shipping (may be free based on subtotal)
        $shipping = $this->calculateShipping($subtotal, $shippingCost);

        // Calculate tax on (subtotal - discount), excluding shipping
        $taxableAmount = max(0, $subtotal - $discount);
        $tax = $this->calculateTax($taxableAmount);

        // Total = subtotal - discount + tax + shipping
        $total = $subtotal - $discount + $tax + $shipping;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round(max(0, $total), 2), // Ensure total never goes negative
        ];
    }

    /**
     * Calculate shipping cost, applying free shipping threshold if applicable.
     *
     * @param float $subtotal The cart subtotal
     * @param float $baseCost The base shipping cost
     * @return float The final shipping cost
     */
    private function calculateShipping(float $subtotal, float $baseCost): float
    {
        $freeThreshold = Setting::get('free_shipping_threshold', 0);

        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
            return 0;
        }

        return $baseCost;
    }

    /**
     * Calculate tax based on taxable amount.
     *
     * @param float $taxableAmount The amount to calculate tax on
     * @return float The calculated tax amount
     */
    private function calculateTax(float $taxableAmount): float
    {
        $taxEnabled = Setting::get('tax_enabled', false);
        $taxIncluded = Setting::get('tax_included_in_prices', true);

        if (!$taxEnabled || $taxIncluded) {
            return 0;
        }

        $taxRate = Setting::get('tax_rate', 21);
        return $taxableAmount * ($taxRate / 100);
    }
}

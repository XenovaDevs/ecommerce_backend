<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\Setting;

/**
 * @ai-context OrderCalculationService handles order total calculations.
 */
class OrderCalculationService
{
    public function calculate(Cart $cart, float $shippingCost = 0): array
    {
        $subtotal = $cart->subtotal;
        $shipping = $this->calculateShipping($subtotal, $shippingCost);
        $tax = $this->calculateTax($subtotal);
        $discount = 0;
        $total = $subtotal + $shipping + $tax - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }

    private function calculateShipping(float $subtotal, float $baseCost): float
    {
        $freeThreshold = Setting::get('free_shipping_threshold', 0);

        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
            return 0;
        }

        return $baseCost;
    }

    private function calculateTax(float $subtotal): float
    {
        $taxEnabled = Setting::get('tax_enabled', false);
        $taxIncluded = Setting::get('tax_included_in_prices', true);

        if (!$taxEnabled || $taxIncluded) {
            return 0;
        }

        $taxRate = Setting::get('tax_rate', 21);
        return $subtotal * ($taxRate / 100);
    }
}

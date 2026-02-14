<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\DTOs\Order\CreateOrderDTO;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Exceptions\Domain\InvalidOperationException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Payment\PaymentService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @ai-context OrderService handles all order-related business logic.
 *             Orchestrates order creation including payment and coupon processing.
 */
class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderCalculationService $calculationService,
        private readonly CouponService $couponService,
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Create an order from the user's cart.
     * This orchestrates the entire checkout process including:
     * - Cart validation
     * - Coupon validation and application
     * - Order creation
     * - Stock reduction
     * - Coupon usage recording
     * - Payment preference creation
     * - Cart clearing
     *
     * @param User|null $user The user creating the order (null for guest checkout)
     * @param CreateOrderDTO $dto Order creation data
     * @param string|null $sessionId Session identifier for guest checkout carts
     * @return array Contains 'order' and 'payment_url'
     * @throws InvalidOperationException If cart is invalid or checkout fails
     */
    public function createFromCart(?User $user, CreateOrderDTO $dto, ?string $sessionId = null): array
    {
        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $cart->loadMissing(['items.product', 'coupons']);

        if (!$cart || $cart->is_empty) {
            throw new InvalidOperationException('Cart is empty', 'EMPTY_CART');
        }

        // Validate cart items (stock availability, prices)
        $errors = $this->cartService->validateCart($cart);
        if (!empty($errors)) {
            throw new InvalidOperationException(
                'Some items in your cart are no longer available',
                'CART_VALIDATION_FAILED',
                ['items' => $errors]
            );
        }

        // Validate applied coupons are still valid
        $this->validateCartCoupons($cart);

        return DB::transaction(function () use ($user, $cart, $dto) {
            // Create addresses
            $shippingAddress = $this->createOrderAddress($dto->shippingAddress, 'shipping');
            $billingAddress = $dto->billingAddress
                ? $this->createOrderAddress($dto->billingAddress, 'billing')
                : $shippingAddress;

            // Calculate totals including coupon discounts
            $totals = $this->calculationService->calculate($cart, $dto->shippingCost);

            // Create order
            $order = Order::create([
                'user_id' => $user?->id,
                'status' => OrderStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'subtotal' => $totals['subtotal'],
                'shipping_cost' => $totals['shipping'],
                'tax' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'notes' => $dto->notes,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress->id,
            ]);

            // Create order items and decrease stock
            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->product->name,
                    'sku' => $item->variant?->sku ?? $item->product->sku,
                    'quantity' => $item->quantity,
                    'price' => $item->current_price,
                    'total' => $item->total,
                    'options' => $item->variant?->attributes,
                ]);

                $item->product->decreaseStock($item->quantity, $item->variant_id);
            }

            // Record coupon usage
            $this->recordCouponUsage($cart, $user, $order);

            // Add order status history
            $order->addStatusHistory('Order created');

            // Create payment preference
            $paymentUrl = null;
            if ($dto->paymentMethod === 'mercadopago') {
                try {
                    $guestPayer = null;

                    if (!$user) {
                        $guestPayer = [
                            'name' => $dto->shippingAddress['name'] ?? 'Cliente',
                            'email' => $dto->shippingAddress['email'] ?? '',
                        ];
                    }

                    $paymentPreference = $this->paymentService->createPaymentPreference(
                        $user,
                        $order->id,
                        $guestPayer
                    );
                    $paymentUrl = $paymentPreference['init_point'];

                    Log::info('Payment preference created for order', [
                        'order_id' => $order->id,
                        'payment_id' => $paymentPreference['payment_id'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create payment preference', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);

                    // Continue without payment URL - user can retry payment later
                    // Don't throw exception to avoid blocking order creation
                }
            }

            // Clear cart after successful order creation
            $cart->clear();

            return [
                'order' => $order->load(['items', 'shippingAddress', 'billingAddress']),
                'payment_url' => $paymentUrl,
            ];
        });
    }

    /**
     * Validate that all coupons applied to the cart are still valid.
     *
     * @param Cart $cart
     * @return void
     * @throws InvalidOperationException If any coupon is invalid
     */
    private function validateCartCoupons(Cart $cart): void
    {
        foreach ($cart->coupons as $coupon) {
            try {
                $this->couponService->validateCoupon($coupon->code, $cart->subtotal);
            } catch (\Exception $e) {
                throw new InvalidOperationException(
                    "Coupon '{$coupon->code}' is no longer valid: {$e->getMessage()}",
                    'INVALID_COUPON',
                    ['coupon_code' => $coupon->code]
                );
            }
        }
    }

    /**
     * Record usage for all coupons applied to the cart.
     *
     * @param Cart $cart
     * @param User|null $user
     * @param Order $order
     * @return void
     */
    private function recordCouponUsage(Cart $cart, ?User $user, Order $order): void
    {
        foreach ($cart->coupons as $coupon) {
            $discountAmount = $coupon->calculateDiscount($cart->subtotal);

            if ($user) {
                $this->couponService->recordCouponUsage(
                    $order,
                    $user,
                    $coupon,
                    $discountAmount
                );
            } else {
                // Keep max-uses accounting for guest checkout even without a user account.
                $coupon->incrementUsage();
            }

            Log::info('Coupon usage recorded', [
                'order_id' => $order->id,
                'coupon_code' => $coupon->code,
                'discount_amount' => $discountAmount,
            ]);
        }
    }

    public function findById(int $id, ?int $userId = null): Order
    {
        $query = Order::with(['items', 'shippingAddress', 'billingAddress', 'payment', 'shipment']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $order = $query->find($id);

        if (!$order) {
            throw new EntityNotFoundException('Order', $id);
        }

        return $order;
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::forUser($userId)
            ->with(['items'])
            ->recent()
            ->paginate($perPage);
    }

    public function cancel(Order $order, ?int $userId = null): Order
    {
        if ($userId && $order->user_id !== $userId) {
            throw new EntityNotFoundException('Order', $order->id);
        }

        if (!$order->canBeCancelled()) {
            throw new InvalidOperationException(
                'This order cannot be cancelled',
                'ORDER_CANNOT_BE_CANCELLED'
            );
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increaseStock($item->quantity, $item->variant_id);
                }
            }

            $order->updateStatus(OrderStatus::CANCELLED, 'Cancelled by customer');

            return $order->fresh();
        });
    }

    public function updateStatus(Order $order, OrderStatus $status, ?string $notes = null, ?int $changedBy = null): Order
    {
        $order->updateStatus($status, $notes, $changedBy);
        return $order->fresh();
    }

    public function processOrder(Order $order): void
    {
        // Additional order processing logic
        // Send notifications, update analytics, etc.
    }

    private function createOrderAddress(array $data, string $type): OrderAddress
    {
        return OrderAddress::create([
            'type' => $type,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'],
            'country' => $data['country'] ?? 'AR',
        ]);
    }
}

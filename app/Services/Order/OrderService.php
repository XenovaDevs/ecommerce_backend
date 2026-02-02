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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @ai-context OrderService handles all order-related business logic.
 */
class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderCalculationService $calculationService
    ) {}

    public function createFromCart(User $user, CreateOrderDTO $dto): Order
    {
        $cart = Cart::forUser($user->id)->first();

        if (!$cart || $cart->is_empty) {
            throw new InvalidOperationException('Cart is empty', 'EMPTY_CART');
        }

        $errors = $this->cartService->validateCart($cart);
        if (!empty($errors)) {
            throw new InvalidOperationException(
                'Some items in your cart are no longer available',
                'CART_VALIDATION_FAILED',
                ['items' => $errors]
            );
        }

        return DB::transaction(function () use ($user, $cart, $dto) {
            $shippingAddress = $this->createOrderAddress($dto->shippingAddress, 'shipping');
            $billingAddress = $dto->billingAddress
                ? $this->createOrderAddress($dto->billingAddress, 'billing')
                : $shippingAddress;

            $totals = $this->calculationService->calculate($cart, $dto->shippingCost ?? 0);

            $order = Order::create([
                'user_id' => $user->id,
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

            $order->addStatusHistory('Order created');

            $cart->clear();

            return $order->load(['items', 'shippingAddress', 'billingAddress']);
        });
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

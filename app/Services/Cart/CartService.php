<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Exceptions\Domain\EntityNotFoundException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidOperationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @ai-context CartService handles all shopping cart business logic.
 */
class CartService
{
    public function getOrCreateCart(?User $user, ?string $sessionId = null): Cart
    {
        if ($user) {
            $cart = Cart::forUser($user->id)->first();
            if ($cart) {
                return $cart;
            }
        }

        if ($sessionId) {
            $cart = Cart::forSession($sessionId)->notExpired()->first();
            if ($cart) {
                return $cart;
            }
        }

        $sessionId = $sessionId ?? Str::uuid()->toString();

        return Cart::create([
            'user_id' => $user?->id,
            'session_id' => $sessionId,
            'expires_at' => $user ? null : now()->addDays(7),
        ]);
    }

    public function addItem(Cart $cart, int $productId, int $quantity = 1, ?int $variantId = null): CartItem
    {
        $product = Product::find($productId);

        if (!$product || !$product->is_active) {
            throw new EntityNotFoundException('Product', $productId);
        }

        $this->validateStock($product, $quantity, $variantId);

        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;
            $this->validateStock($product, $newQuantity, $variantId);

            $existingItem->update(['quantity' => $newQuantity]);
            return $existingItem->fresh();
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'price_at_addition' => $this->getItemPrice($product, $variantId),
        ]);
    }

    public function updateItemQuantity(Cart $cart, int $itemId, int $quantity): CartItem
    {
        $item = $cart->items()->find($itemId);

        if (!$item) {
            throw new EntityNotFoundException('CartItem', $itemId);
        }

        if ($quantity <= 0) {
            $item->delete();
            throw new InvalidOperationException('Item removed from cart', 'ITEM_REMOVED');
        }

        $this->validateStock($item->product, $quantity, $item->variant_id);

        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    public function removeItem(Cart $cart, int $itemId): void
    {
        $item = $cart->items()->find($itemId);

        if (!$item) {
            throw new EntityNotFoundException('CartItem', $itemId);
        }

        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->clear();
    }

    public function mergeGuestCart(User $user, string $sessionId): void
    {
        $guestCart = Cart::forSession($sessionId)->notExpired()->first();
        if (!$guestCart) {
            return;
        }

        $userCart = $this->getOrCreateCart($user);

        foreach ($guestCart->items as $item) {
            try {
                $this->addItem(
                    $userCart,
                    $item->product_id,
                    $item->quantity,
                    $item->variant_id
                );
            } catch (\Exception) {
                // Skip items that can't be merged
            }
        }

        $guestCart->delete();
    }

    public function validateCart(Cart $cart): array
    {
        $errors = [];

        foreach ($cart->items as $item) {
            if (!$item->product || !$item->product->is_active) {
                $errors[] = [
                    'item_id' => $item->id,
                    'error' => 'Product no longer available',
                ];
                continue;
            }

            if (!$item->is_available) {
                $errors[] = [
                    'item_id' => $item->id,
                    'error' => 'Insufficient stock',
                    'available' => $item->available_stock,
                ];
            }
        }

        return $errors;
    }

    private function validateStock(Product $product, int $quantity, ?int $variantId): void
    {
        if (!$product->track_stock) {
            return;
        }

        $availableStock = $variantId
            ? $product->variants()->find($variantId)?->stock ?? 0
            : $product->stock;

        if ($quantity > $availableStock) {
            throw new InsufficientStockException(
                $product->name,
                $quantity,
                $availableStock
            );
        }
    }

    private function getItemPrice(Product $product, ?int $variantId): float
    {
        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            return $variant?->price ?? $product->current_price;
        }

        return $product->current_price;
    }
}

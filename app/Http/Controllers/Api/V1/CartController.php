<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Messages\SuccessMessages;
use App\Services\Cart\CartService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context CartController handles shopping cart API endpoints.
 */
class CartController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly CartService $cartService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        return $this->success(new CartResource($cart->load('items.product', 'items.variant')));
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $this->cartService->addItem(
            $cart,
            $request->validated('product_id'),
            $request->validated('quantity', 1),
            $request->validated('variant_id')
        );

        return $this->created(
            new CartResource($cart->fresh()->load('items.product', 'items.variant')),
            SuccessMessages::CART['ITEM_ADDED']
        );
    }

    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $this->cartService->updateItemQuantity(
            $cart,
            $id,
            $request->validated('quantity')
        );

        return $this->success(
            new CartResource($cart->fresh()->load('items.product', 'items.variant'))
        );
    }

    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $this->cartService->removeItem($cart, $id);

        return $this->noContent();
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $this->cartService->clear($cart);

        return $this->success(
            new CartResource($cart->fresh()->load('items.product', 'items.variant')),
            SuccessMessages::CART['CLEARED']
        );
    }

    public function merge(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('User must be authenticated to merge cart', 401);
        }

        $sessionId = $request->input('session_id') ?? $request->header('X-Session-ID');

        if (!$sessionId) {
            return $this->error('Session ID is required', 400);
        }

        $this->cartService->mergeGuestCart($user, $sessionId);

        $cart = $this->cartService->getOrCreateCart($user);

        return $this->success(
            new CartResource($cart->fresh()->load('items.product', 'items.variant')),
            SuccessMessages::CART['MERGED'] ?? 'Cart merged successfully'
        );
    }
}

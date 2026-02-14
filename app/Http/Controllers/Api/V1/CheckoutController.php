<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Order\CreateOrderDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Messages\SuccessMessages;
use App\Services\Cart\CartService;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ai-context CheckoutController handles checkout-specific API endpoints.
 */
class CheckoutController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService,
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Validate cart before checkout
     */
    public function validate(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $errors = $this->cartService->validateCart($cart);

        if (empty($errors)) {
            return $this->success([
                'valid' => true,
                'message' => 'Cart is valid',
            ]);
        }

        return $this->error('Cart validation failed', 'CART_VALIDATION_FAILED', 422, [
            'valid' => false,
            'errors' => $errors,
        ]);
    }

    /**
     * Validate cart before guest checkout.
     */
    public function validateGuest(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-ID');

        if (!$sessionId) {
            return $this->error(
                'Missing session ID for guest checkout',
                'SESSION_ID_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $cart = $this->cartService->getOrCreateCart(null, $sessionId);
        $errors = $this->cartService->validateCart($cart);

        if (empty($errors)) {
            return $this->success([
                'valid' => true,
                'message' => 'Cart is valid',
            ]);
        }

        return $this->error('Cart validation failed', 'CART_VALIDATION_FAILED', 422, [
            'valid' => false,
            'errors' => $errors,
        ]);
    }

    /**
     * Process checkout and create order
     */
    public function process(CheckoutRequest $request): JsonResponse
    {
        $dto = CreateOrderDTO::fromRequest($request);
        $result = $this->orderService->createFromCart(
            $request->user(),
            $dto,
            $request->header('X-Session-ID')
        );

        $responseData = [
            'order' => new OrderResource($result['order']),
            'payment_url' => $result['payment_url'],
        ];

        return $this->created(
            $responseData,
            SuccessMessages::ORDER['CREATED']
        );
    }

    /**
     * Process guest checkout and create order from session cart.
     */
    public function processGuest(CheckoutRequest $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-ID');

        if (!$sessionId) {
            return $this->error(
                'Missing session ID for guest checkout',
                'SESSION_ID_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $dto = CreateOrderDTO::fromRequest($request);
        $result = $this->orderService->createFromCart(null, $dto, $sessionId);

        $responseData = [
            'order' => new OrderResource($result['order']),
            'payment_url' => $result['payment_url'],
        ];

        return $this->created(
            $responseData,
            SuccessMessages::ORDER['CREATED']
        );
    }

    /**
     * Create payment preference for checkout
     */
    public function createPaymentPreference(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $preference = $this->paymentService->createPaymentPreference(
            $request->user(),
            (int) $request->input('order_id')
        );

        return $this->success($preference);
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        $methods = [
            [
                'id' => 'mercadopago',
                'name' => 'Mercado Pago',
                'type' => 'online',
                'icon' => 'mercadopago',
                'description' => 'Paga con tarjeta, débito o efectivo',
                'enabled' => true,
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Transferencia Bancaria',
                'type' => 'offline',
                'icon' => 'bank',
                'description' => 'Transferencia o depósito bancario',
                'enabled' => false,
            ],
            [
                'id' => 'cash',
                'name' => 'Efectivo contra entrega',
                'type' => 'cash_on_delivery',
                'icon' => 'cash',
                'description' => 'Paga en efectivo al recibir',
                'enabled' => false,
            ],
        ];

        return $this->success($methods);
    }
}

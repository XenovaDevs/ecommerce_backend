<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context PaymentController handles payment API endpoints.
 */
class PaymentController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function create(Request $request): JsonResponse
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
     * Get payment status. Optionally sync with Mercado Pago if there's a discrepancy.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function status(int $id, Request $request): JsonResponse
    {
        // Check if sync parameter is provided
        $syncWithGateway = $request->boolean('sync', false);

        $payment = $this->paymentService->getPaymentStatus($id, $syncWithGateway);

        return $this->success($payment);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Order\CreateOrderDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Messages\SuccessMessages;
use App\Services\Order\OrderService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context OrderController handles customer order API endpoints.
 */
class OrderController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->listForUser(
            $request->user()->id,
            (int) $request->input('per_page', 15)
        );

        return $this->paginated(OrderResource::collection($orders));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findById($id, $request->user()->id);

        return $this->success(new OrderResource($order));
    }

    public function checkout(CreateOrderRequest $request): JsonResponse
    {
        $dto = CreateOrderDTO::fromRequest($request);
        $order = $this->orderService->createFromCart($request->user(), $dto);

        return $this->created(
            new OrderResource($order),
            SuccessMessages::ORDER['CREATED']
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findById($id, $request->user()->id);
        $order = $this->orderService->cancel($order, $request->user()->id);

        return $this->success(
            new OrderResource($order),
            SuccessMessages::ORDER['CANCELLED']
        );
    }
}

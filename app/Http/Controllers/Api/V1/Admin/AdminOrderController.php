<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminOrderController handles admin order management.
 */
class AdminOrderController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(OrderResource::collection($orders));
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->findById($id);

        return $this->success(new OrderResource($order));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', OrderStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orderService->findById($id);
        $order = $this->orderService->updateStatus(
            $order,
            OrderStatus::from($validated['status']),
            $validated['notes'] ?? null,
            $request->user()->id
        );

        return $this->success(new OrderResource($order));
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', OrderStatus::PENDING)->count(),
            'processing_orders' => Order::where('status', OrderStatus::PROCESSING)->count(),
            'completed_orders' => Order::where('status', OrderStatus::DELIVERED)->count(),
            'total_revenue' => (float) Order::where('status', OrderStatus::DELIVERED)->sum('total'),
        ];

        return $this->success($stats);
    }
}

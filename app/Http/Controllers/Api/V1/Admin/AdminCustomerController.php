<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminCustomerController handles admin customer management.
 */
class AdminCustomerController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', UserRole::CUSTOMER)
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => function ($query) {
                    $query->where('status', OrderStatus::DELIVERED);
                }
            ], 'total');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(UserResource::collection($customers));
    }

    public function show(int $id): JsonResponse
    {
        $customer = User::where('role', UserRole::CUSTOMER)
            ->with(['orders', 'addresses'])
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => function ($query) {
                    $query->where('status', OrderStatus::DELIVERED);
                }
            ], 'total')
            ->findOrFail($id);

        return $this->success(new UserResource($customer));
    }

    public function orders(int $id): JsonResponse
    {
        $customer = User::where('role', UserRole::CUSTOMER)
            ->findOrFail($id);

        $orders = $customer->orders()
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->paginated(OrderResource::collection($orders));
    }
}

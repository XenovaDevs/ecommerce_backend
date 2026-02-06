<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Enums\UserRole;
use App\Http\Controllers\Controller;
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
                    $query->where('status', \App\Domain\Enums\OrderStatus::DELIVERED);
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

        $data = $customers->map(function ($customer) {
            $customerData = (new UserResource($customer))->resolve();
            $customerData['orders_count'] = $customer->orders_count;
            $customerData['total_spent'] = (float) ($customer->total_spent ?? 0);
            return $customerData;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $customers->currentPage(),
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $customer = User::where('role', UserRole::CUSTOMER)
            ->with(['orders', 'addresses'])
            ->withCount('orders')
            ->findOrFail($id);

        // Calculate total spent from completed orders
        $totalSpent = $customer->orders()
            ->where('status', \App\Domain\Enums\OrderStatus::DELIVERED)
            ->sum('total');

        $data = (new UserResource($customer))->resolve();
        $data['orders_count'] = $customer->orders_count;
        $data['total_spent'] = (float) $totalSpent;

        return $this->success($data);
    }

    public function orders(int $id): JsonResponse
    {
        $customer = User::where('role', UserRole::CUSTOMER)
            ->findOrFail($id);

        $orders = $customer->orders()
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\OrderResource::collection($orders)->resolve(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }
}

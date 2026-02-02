<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @ai-context DashboardController handles admin dashboard statistics.
 */
class DashboardController extends Controller
{
    use HasApiResponse;

    public function index(): JsonResponse
    {
        // Basic stats for test compatibility
        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::sum('total'),
            'total_customers' => User::where('role', UserRole::CUSTOMER)->count(),
            'total_products' => Product::count(),

            // Extended stats
            'orders' => [
                'total' => Order::count(),
                'pending' => Order::where('status', OrderStatus::PENDING)->count(),
                'processing' => Order::where('status', OrderStatus::PROCESSING)->count(),
                'completed' => Order::where('status', OrderStatus::DELIVERED)->count(),
            ],
            'revenue' => [
                'total' => Order::where('payment_status', PaymentStatus::PAID)->sum('total'),
                'today' => Order::where('payment_status', PaymentStatus::PAID)
                    ->whereDate('created_at', today())
                    ->sum('total'),
                'this_month' => Order::where('payment_status', PaymentStatus::PAID)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total'),
            ],
            'products' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'low_stock' => Product::where('track_stock', true)
                    ->where('stock', '<=', 5)
                    ->count(),
                'out_of_stock' => Product::where('track_stock', true)
                    ->where('stock', 0)
                    ->count(),
            ],
            'customers' => [
                'total' => User::where('role', UserRole::CUSTOMER)->count(),
                'new_today' => User::where('role', UserRole::CUSTOMER)
                    ->whereDate('created_at', today())
                    ->count(),
            ],
            'recent_orders' => Order::with(['user'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user?->name,
                    'total' => $order->total,
                    'status' => $order->status->value,
                    'created_at' => $order->created_at->toIso8601String(),
                ]),
        ];

        return $this->success($stats);
    }
}

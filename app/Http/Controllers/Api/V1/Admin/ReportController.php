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
use Illuminate\Http\Request;

/**
 * @ai-context ReportController handles admin reporting and analytics.
 */
class ReportController extends Controller
{
    use HasApiResponse;

    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // Only include completed (delivered) orders
        $query = Order::where('status', OrderStatus::DELIVERED);

        // Handle both naming conventions for date filtering
        $startDate = $request->input('from') ?? $request->input('start_date');
        $endDate = $request->input('to') ?? $request->input('end_date');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Clone query for accurate counts
        $totalOrders = (clone $query)->count();
        $totalSales = (float) (clone $query)->sum('total');
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        $sales = [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'average_order_value' => round($averageOrderValue, 2),
            'total_revenue' => $totalSales, // Alias for compatibility
            'daily_sales' => (clone $query)->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return $this->success($sales);
    }

    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) $request->input('limit', 10);

        $topProducts = Product::withCount([
            'orderItems' => function ($query) use ($request) {
                if ($request->filled('start_date')) {
                    $query->whereHas('order', fn ($q) =>
                        $q->whereDate('created_at', '>=', $request->input('start_date'))
                    );
                }
                if ($request->filled('end_date')) {
                    $query->whereHas('order', fn ($q) =>
                        $q->whereDate('created_at', '<=', $request->input('end_date'))
                    );
                }
            }
        ])
            ->withSum('orderItems', 'total')
            ->orderBy('order_items_count', 'desc')
            ->limit($limit)
            ->get();

        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $lowStock = Product::where('stock', '<=', 10)->where('stock', '>', 0)->count();
        $outOfStock = Product::where('stock', 0)->count();

        return $this->success([
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'top_products' => $topProducts,
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'period' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // Get the period in days (defaults to 30)
        $period = (int) $request->input('period', 30);

        $totalCustomers = User::where('role', UserRole::CUSTOMER)->count();

        // New customers within the specified period
        $newCustomers = User::where('role', UserRole::CUSTOMER)
            ->whereDate('created_at', '>=', now()->subDays($period))
            ->count();

        $topCustomers = User::where('role', UserRole::CUSTOMER)
            ->withSum('orders', 'total')
            ->orderBy('orders_sum_total', 'desc')
            ->limit(10)
            ->get();

        return $this->success([
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'top_customers' => $topCustomers,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:sales,products,customers'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $type = $request->input('type');
        $format = $request->input('format', 'csv');

        // Generate filename
        $filename = "{$type}_report_" . now()->format('Y-m-d_His') . ".{$format}";

        // For MVP, return a simple message
        // In production, use Laravel Excel or similar
        return $this->success([
            'message' => 'Export functionality coming soon',
            'filename' => $filename,
            'type' => $type,
            'format' => $format,
        ]);
    }
}

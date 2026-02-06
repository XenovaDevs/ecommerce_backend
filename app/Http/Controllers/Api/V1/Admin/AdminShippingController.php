<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context AdminShippingController handles shipping rates management.
 */
class AdminShippingController extends Controller
{
    use HasApiResponse;

    public function rates(Request $request): JsonResponse
    {
        // Mock shipping rates for MVP
        // In production, integrate with real shipping API (Andreani, etc.)
        $rates = [
            [
                'id' => 1,
                'name' => 'Estándar',
                'carrier' => 'Andreani',
                'delivery_time' => '3-5 días hábiles',
                'base_cost' => 500.00,
                'free_shipping_threshold' => 5000.00,
            ],
            [
                'id' => 2,
                'name' => 'Express',
                'carrier' => 'Andreani',
                'delivery_time' => '24-48 horas',
                'base_cost' => 800.00,
                'free_shipping_threshold' => 10000.00,
            ],
            [
                'id' => 3,
                'name' => 'Retiro en sucursal',
                'carrier' => 'Andreani',
                'delivery_time' => '2-3 días hábiles',
                'base_cost' => 0.00,
                'free_shipping_threshold' => 0.00,
            ],
        ];

        return $this->success($rates);
    }
}

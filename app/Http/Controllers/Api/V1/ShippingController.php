<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @ai-context ShippingController handles shipping quote API endpoint.
 */
class ShippingController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ShippingService $shippingService
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'max:10'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quote = $this->shippingService->quote(
            postalCode: $validated['postal_code'],
            weight: (float) ($validated['weight'] ?? 0),
            declaredValue: (float) ($validated['declared_value'] ?? 0),
        );

        return $this->success($quote, 'Shipping quote retrieved successfully');
    }

    public function track(Request $request, string $trackingNumber): JsonResponse
    {
        try {
            $tracking = $this->shippingService->trackShipment($trackingNumber);

            return $this->success($tracking, 'Tracking information retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}

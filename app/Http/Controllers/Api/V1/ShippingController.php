<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingService;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @ai-context ShippingController handles shipping quote API endpoint.
 */
class ShippingController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ShippingService $shippingService
    ) {}

    /**
     * Get shipping quote for postal code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'max:10'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $quote = $this->shippingService->quote(
                postalCode: $validated['postal_code'],
                weight: (float) ($validated['weight'] ?? 1.0),
                declaredValue: (float) ($validated['declared_value'] ?? 0),
            );

            return $this->success($quote, 'Shipping quote retrieved successfully');
        } catch (\Exception $e) {
            Log::warning('Shipping quote request failed', [
                'postal_code' => $validated['postal_code'],
                'error' => $e->getMessage(),
            ]);

            // Return default quote on error - better UX than showing error
            return $this->success(
                $this->getDefaultQuote(),
                'Shipping quote retrieved (fallback)'
            );
        }
    }

    /**
     * Track shipment by tracking number.
     *
     * @param Request $request
     * @param string $trackingNumber
     * @return JsonResponse
     */
    public function track(Request $request, string $trackingNumber): JsonResponse
    {
        try {
            $tracking = $this->shippingService->trackShipment($trackingNumber);

            return $this->success(
                $tracking,
                'Tracking information retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::warning('Tracking request failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'Unable to retrieve tracking information. Please try again later.',
                404,
                ['tracking_number' => $trackingNumber]
            );
        }
    }

    /**
     * Get default shipping quote as fallback.
     *
     * @return array
     */
    private function getDefaultQuote(): array
    {
        return [
            'provider' => 'standard',
            'options' => [
                [
                    'service_code' => 'standard',
                    'service_name' => 'Envío Estándar',
                    'cost' => 2500,
                    'estimated_days' => 5,
                    'description' => 'Envío estándar a domicilio',
                ],
            ],
            'free_threshold' => 50000,
        ];
    }
}

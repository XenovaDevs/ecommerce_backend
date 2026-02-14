<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Domain\Enums\ShippingStatus;
use App\Exceptions\Shipping\ShippingCreationException;
use App\Exceptions\Shipping\ShippingQuoteException;
use App\Models\Order;
use App\Services\Shipping\Contracts\ShippingProviderInterface;
use App\Services\Shipping\DTOs\ShipmentCreationResponse;
use App\Services\Shipping\DTOs\ShippingOption;
use App\Services\Shipping\DTOs\ShippingQuoteRequest;
use App\Services\Shipping\DTOs\ShippingQuoteResponse;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResponse;
use App\Services\Shipping\Exceptions\AndreaniApiException;
use App\Services\Shipping\Exceptions\ShippingTrackingException;
use Illuminate\Support\Facades\Log;

/**
 * Andreani shipping provider implementation.
 * Single Responsibility: Business logic for Andreani shipping operations.
 */
class AndreaniShippingProvider implements ShippingProviderInterface
{
    private const PROVIDER_NAME = 'andreani';
    private const DEFAULT_PACKAGE_TYPE = 'paquete';
    private const DEFAULT_SERVICE_TYPE = 'Estándar';

    public function __construct(
        private readonly AndreaniApiClient $apiClient,
        private readonly string $contractNumber,
    ) {
    }

    /**
     * Get shipping quote from Andreani.
     */
    public function getQuote(ShippingQuoteRequest $request): ShippingQuoteResponse
    {
        try {
            $payload = $this->buildQuotePayload($request);
            $response = $this->apiClient->getShippingQuote($payload);

            $options = $this->parseQuoteOptions($response);

            return new ShippingQuoteResponse(
                provider: self::PROVIDER_NAME,
                options: $options,
            );
        } catch (AndreaniApiException $e) {
            throw new ShippingQuoteException(
                "Andreani quote failed: {$e->getMessage()}",
                [
                    'http_status' => $e->getHttpStatus(),
                    'response' => $e->getResponseData(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error in Andreani quote', [
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]);

            throw new ShippingQuoteException(
                'An unexpected error occurred while getting shipping quote'
            );
        }
    }

    /**
     * Create shipment in Andreani.
     */
    public function createShipment(Order $order): ShipmentCreationResponse
    {
        try {
            $payload = $this->buildShipmentPayload($order);
            $response = $this->apiClient->createShipment($payload);

            return ShipmentCreationResponse::fromAndreaniResponse($response);
        } catch (AndreaniApiException $e) {
            throw new ShippingCreationException(
                "Andreani shipment creation failed: {$e->getMessage()}",
                [
                    'order_id' => $order->id,
                    'http_status' => $e->getHttpStatus(),
                    'response' => $e->getResponseData(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error in Andreani shipment creation', [
                'exception' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            throw new ShippingCreationException(
                'An unexpected error occurred while creating shipment',
                ['order_id' => $order->id]
            );
        }
    }

    /**
     * Track shipment in Andreani.
     */
    public function trackShipment(string $trackingNumber): TrackingResponse
    {
        try {
            $response = $this->apiClient->getTracking($trackingNumber);

            return $this->parseTrackingResponse($trackingNumber, $response);
        } catch (AndreaniApiException $e) {
            throw new ShippingTrackingException(
                "Andreani tracking failed: {$e->getMessage()}",
                [
                    'tracking_number' => $trackingNumber,
                    'http_status' => $e->getHttpStatus(),
                    'response' => $e->getResponseData(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error in Andreani tracking', [
                'exception' => $e->getMessage(),
                'tracking_number' => $trackingNumber,
            ]);

            throw new ShippingTrackingException(
                'An unexpected error occurred while tracking shipment',
                ['tracking_number' => $trackingNumber]
            );
        }
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * Map Andreani status to internal ShippingStatus.
     */
    public function mapStatus(string $andreaniStatus): ShippingStatus
    {
        return match (strtolower($andreaniStatus)) {
            'en preparacion', 'ingresado' => ShippingStatus::PENDING,
            'en camino', 'en transito' => ShippingStatus::IN_TRANSIT,
            'despachado', 'en distribucion' => ShippingStatus::SHIPPED,
            'entregado' => ShippingStatus::DELIVERED,
            'devuelto', 'rechazado', 'no entregado' => ShippingStatus::FAILED,
            default => ShippingStatus::PENDING,
        };
    }

    /**
     * Build quote request payload for Andreani API.
     */
    private function buildQuotePayload(ShippingQuoteRequest $request): array
    {
        $payload = [
            'cpDestino' => $request->destinationPostalCode,
            'cpOrigen' => $request->originPostalCode,
            'contrato' => $this->contractNumber,
            'peso' => $request->weight,
            'valorDeclarado' => $request->declaredValue,
            'bultos' => [
                [
                    'kilos' => $request->weight,
                    'valorDeclarado' => $request->declaredValue,
                ]
            ],
        ];

        if ($request->volumeInCm3) {
            $payload['bultos'][0]['volumen'] = $request->volumeInCm3;
        }

        return $payload;
    }

    /**
     * Parse quote options from Andreani response.
     *
     * @return ShippingOption[]
     */
    private function parseQuoteOptions(array $response): array
    {
        $tarifas = $response['tarifas'] ?? [];

        if (empty($tarifas)) {
            return [];
        }

        return array_map(
            fn(array $tarifa) => ShippingOption::fromAndreaniResponse($tarifa),
            $tarifas
        );
    }

    /**
     * Build shipment creation payload for Andreani API.
     */
    private function buildShipmentPayload(Order $order): array
    {
        $order->loadMissing(['shippingAddress', 'items.product']);

        $shippingAddress = $order->shippingAddress;

        if (!$shippingAddress) {
            throw new \RuntimeException('Order has no shipping address');
        }

        // Calculate total weight from order items
        $totalWeight = $order->items->sum(function ($item) {
            return ($item->product->weight ?? 0.5) * $item->quantity;
        });

        return [
            'contrato' => $this->contractNumber,
            'origen' => [
                'postal' => [
                    'codigoPostal' => config('services.andreani.origin_postal_code'),
                ]
            ],
            'destino' => [
                'postal' => [
                    'codigoPostal' => $shippingAddress->postal_code,
                    'calle' => $shippingAddress->address,
                    'numero' => 'S/N', // OrderAddress no tiene número por separado
                    'localidad' => $shippingAddress->city,
                    'region' => $shippingAddress->state,
                    'pais' => $shippingAddress->country ?? 'AR',
                ]
            ],
            'remitente' => [
                'nombreCompleto' => config('app.name'),
                'email' => config('mail.from.address'),
                'documentoTipo' => 'CUIT',
                'documentoNumero' => config('services.andreani.sender_document'),
            ],
            'destinatario' => [
                [
                    'nombreCompleto' => $shippingAddress->name,
                    'email' => $order->user?->email ?? '',
                    'documentoTipo' => 'DNI',
                    'documentoNumero' => '', // OrderAddress no tiene documento
                    'celular' => $shippingAddress->phone ?? '',
                ]
            ],
            'productoAEntregar' => self::DEFAULT_SERVICE_TYPE,
            'bultos' => [
                [
                    'kilos' => max($totalWeight, 0.1), // Minimum 100g
                    'valorDeclarado' => $order->total,
                    'volumen' => $this->calculateVolume($order),
                ]
            ],
            'referencia' => [
                'ordenCompra' => $order->order_number,
            ],
        ];
    }

    /**
     * Calculate volume for order items.
     */
    private function calculateVolume(Order $order): ?int
    {
        // If products have dimensions, calculate real volume
        // Otherwise return null to let Andreani estimate
        return null;
    }

    /**
     * Parse tracking response from Andreani.
     */
    private function parseTrackingResponse(string $trackingNumber, array $response): TrackingResponse
    {
        $events = [];
        $lastUpdate = null;

        if (isset($response['trazas']) && is_array($response['trazas'])) {
            foreach ($response['trazas'] as $traza) {
                $event = TrackingEvent::fromAndreaniResponse($traza);
                $events[] = $event;

                if (!$lastUpdate || $event->timestamp > $lastUpdate) {
                    $lastUpdate = $event->timestamp;
                }
            }
        }

        // Determine current status from latest event
        $currentStatus = 'pending';
        if (!empty($events)) {
            $latestEvent = $events[0];
            $currentStatus = strtolower($latestEvent->status);
        }

        return new TrackingResponse(
            trackingNumber: $trackingNumber,
            status: $currentStatus,
            events: $events,
            lastUpdate: $lastUpdate,
        );
    }
}

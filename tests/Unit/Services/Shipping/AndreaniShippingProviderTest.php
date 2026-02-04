<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Shipping;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\ShippingStatus;
use App\Exceptions\Shipping\ShippingCreationException;
use App\Exceptions\Shipping\ShippingQuoteException;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\AndreaniApiClient;
use App\Services\Shipping\AndreaniShippingProvider;
use App\Services\Shipping\DTOs\ShippingQuoteRequest;
use App\Services\Shipping\Exceptions\AndreaniApiException;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for AndreaniShippingProvider.
 * Tests business logic independently of HTTP layer.
 *
 * @group shipping
 * @group unit
 */
class AndreaniShippingProviderTest extends TestCase
{
    private AndreaniApiClient $mockApiClient;
    private AndreaniShippingProvider $provider;
    private string $contractNumber = 'CONTRACT123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock API client
        $this->mockApiClient = Mockery::mock(AndreaniApiClient::class);

        // Create provider instance with mock
        $this->provider = new AndreaniShippingProvider(
            $this->mockApiClient,
            $this->contractNumber
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test: Can get quote successfully and parse response.
     */
    public function test_can_get_quote_successfully(): void
    {
        // Arrange
        $request = new ShippingQuoteRequest(
            originPostalCode: '1419',
            destinationPostalCode: '1426',
            weight: 2.5,
            declaredValue: 15000
        );

        $andreaniResponse = [
            'tarifas' => [
                [
                    'servicio' => 'Estándar',
                    'precio' => 2500.50,
                    'plazoEntrega' => 5,
                    'modalidad' => 'Puerta a Puerta',
                ],
                [
                    'servicio' => 'Express',
                    'precio' => 4500.75,
                    'plazoEntrega' => 2,
                    'modalidad' => 'Puerta a Puerta',
                ],
            ],
        ];

        $this->mockApiClient
            ->shouldReceive('getShippingQuote')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['cpOrigen'] === '1419'
                    && $payload['cpDestino'] === '1426'
                    && $payload['peso'] === 2.5
                    && $payload['valorDeclarado'] === 15000
                    && $payload['contrato'] === $this->contractNumber;
            }))
            ->andReturn($andreaniResponse);

        // Act
        $response = $this->provider->getQuote($request);

        // Assert
        $this->assertEquals('andreani', $response->provider);
        $this->assertCount(2, $response->options);
        $this->assertEquals('Estándar', $response->options[0]->serviceName);
        $this->assertEquals(2500.50, $response->options[0]->cost);
        $this->assertEquals(5, $response->options[0]->estimatedDays);
    }

    /**
     * Test: Throws exception when API call fails.
     */
    public function test_throws_exception_when_quote_api_fails(): void
    {
        // Arrange
        $request = new ShippingQuoteRequest(
            originPostalCode: '1419',
            destinationPostalCode: '1426',
            weight: 2.5,
            declaredValue: 15000
        );

        $this->mockApiClient
            ->shouldReceive('getShippingQuote')
            ->once()
            ->andThrow(new AndreaniApiException('API Error', 503, []));

        // Act & Assert
        $this->expectException(ShippingQuoteException::class);
        $this->expectExceptionMessage('Andreani quote failed');

        $this->provider->getQuote($request);
    }

    /**
     * Test: Can create shipment successfully.
     */
    public function test_can_create_shipment_successfully(): void
    {
        // Arrange
        $order = $this->createMockOrder();

        $andreaniResponse = [
            'numeroAndreani' => 'AND123456789',
            'etiqueta' => 'https://andreani.com/labels/123456789.pdf',
            'fechaEstimadaEntrega' => '2026-02-10',
        ];

        $this->mockApiClient
            ->shouldReceive('createShipment')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return isset($payload['contrato'])
                    && isset($payload['destino'])
                    && isset($payload['destinatario'])
                    && isset($payload['bultos']);
            }))
            ->andReturn($andreaniResponse);

        // Act
        $response = $this->provider->createShipment($order);

        // Assert
        $this->assertEquals('AND123456789', $response->trackingNumber);
        $this->assertEquals('https://andreani.com/labels/123456789.pdf', $response->labelUrl);
        $this->assertNotNull($response->estimatedDelivery);
    }

    /**
     * Test: Throws exception when shipment creation fails.
     */
    public function test_throws_exception_when_shipment_creation_fails(): void
    {
        // Arrange
        $order = $this->createMockOrder();

        $this->mockApiClient
            ->shouldReceive('createShipment')
            ->once()
            ->andThrow(new AndreaniApiException('Creation failed', 422, []));

        // Act & Assert
        $this->expectException(ShippingCreationException::class);
        $this->expectExceptionMessage('Andreani shipment creation failed');

        $this->provider->createShipment($order);
    }

    /**
     * Test: Maps Andreani status to internal ShippingStatus correctly.
     */
    public function test_maps_andreani_status_correctly(): void
    {
        // Test various status mappings
        $statusMappings = [
            'en preparacion' => ShippingStatus::PENDING,
            'En Preparacion' => ShippingStatus::PENDING,
            'ingresado' => ShippingStatus::PENDING,
            'en camino' => ShippingStatus::IN_TRANSIT,
            'en transito' => ShippingStatus::IN_TRANSIT,
            'despachado' => ShippingStatus::SHIPPED,
            'en distribucion' => ShippingStatus::SHIPPED,
            'entregado' => ShippingStatus::DELIVERED,
            'devuelto' => ShippingStatus::FAILED,
            'rechazado' => ShippingStatus::FAILED,
            'no entregado' => ShippingStatus::FAILED,
            'unknown_status' => ShippingStatus::PENDING, // Default fallback
        ];

        foreach ($statusMappings as $andreaniStatus => $expectedStatus) {
            $result = $this->provider->mapStatus($andreaniStatus);
            $this->assertEquals(
                $expectedStatus,
                $result,
                "Failed mapping {$andreaniStatus} to {$expectedStatus->value}"
            );
        }
    }

    /**
     * Test: Provider name is correct.
     */
    public function test_provider_name_is_correct(): void
    {
        $this->assertEquals('andreani', $this->provider->getName());
    }

    /**
     * Test: Quote request includes volume when provided.
     */
    public function test_quote_request_includes_volume_when_provided(): void
    {
        // Arrange
        $request = new ShippingQuoteRequest(
            originPostalCode: '1419',
            destinationPostalCode: '1426',
            weight: 2.5,
            declaredValue: 15000,
            volumeInCm3: 50000 // 50L in cm³
        );

        $andreaniResponse = [
            'tarifas' => [
                [
                    'servicio' => 'Estándar',
                    'precio' => 2500,
                    'plazoEntrega' => 5,
                    'modalidad' => 'Puerta a Puerta',
                ],
            ],
        ];

        $this->mockApiClient
            ->shouldReceive('getShippingQuote')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return isset($payload['bultos'][0]['volumen'])
                    && $payload['bultos'][0]['volumen'] === 50000;
            }))
            ->andReturn($andreaniResponse);

        // Act
        $response = $this->provider->getQuote($request);

        // Assert
        $this->assertCount(1, $response->options);
    }

    /**
     * Test: Shipment creation enforces minimum weight.
     */
    public function test_shipment_creation_enforces_minimum_weight(): void
    {
        // Arrange - Create order with very light product
        $order = $this->createMockOrder(0.01); // 10 grams

        $andreaniResponse = [
            'numeroAndreani' => 'AND123456789',
            'etiqueta' => 'https://andreani.com/labels/123456789.pdf',
        ];

        $this->mockApiClient
            ->shouldReceive('createShipment')
            ->once()
            ->with(Mockery::on(function ($payload) {
                // Should enforce minimum of 0.1 kg
                return $payload['bultos'][0]['kilos'] >= 0.1;
            }))
            ->andReturn($andreaniResponse);

        // Act
        $response = $this->provider->createShipment($order);

        // Assert
        $this->assertNotNull($response->trackingNumber);
    }

    /**
     * Helper: Create a mock order for testing.
     */
    private function createMockOrder(float $productWeight = 1.5): Order
    {
        $user = User::factory()->make(['id' => 1]);
        $product = Product::factory()->make([
            'id' => 1,
            'weight' => $productWeight,
        ]);

        $shippingAddress = OrderAddress::factory()->make([
            'id' => 1,
            'postal_code' => '1426',
            'address' => 'Av. Cabildo 1234',
            'number' => '1234',
            'city' => 'Buenos Aires',
            'state' => 'CABA',
            'country' => 'Argentina',
            'full_name' => 'Juan Pérez',
            'phone' => '1234567890',
            'document_number' => '12345678',
        ]);

        $order = Order::factory()->make([
            'id' => 1,
            'order_number' => 'ORD-260203-TEST',
            'user_id' => $user->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'shipping_address_id' => $shippingAddress->id,
            'total' => 12500,
        ]);

        // Mock relationships
        $order->setRelation('user', $user);
        $order->setRelation('shippingAddress', $shippingAddress);

        $orderItem = OrderItem::factory()->make([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 5000,
        ]);
        $orderItem->setRelation('product', $product);

        $order->setRelation('items', collect([$orderItem]));

        return $order;
    }
}

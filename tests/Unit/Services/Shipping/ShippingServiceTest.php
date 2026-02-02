<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Contracts\ShippingProviderInterface;
use App\Services\Shipping\DTOs\ShipmentCreationResponse;
use App\Services\Shipping\DTOs\ShippingOption;
use App\Services\Shipping\DTOs\ShippingQuoteRequest;
use App\Services\Shipping\DTOs\ShippingQuoteResponse;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ShippingService.
 * Demonstrates SOLID principles - testing against interfaces, not implementations.
 */
class ShippingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShippingProviderInterface $mockProvider;
    private ShippingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the provider interface (Dependency Inversion Principle)
        $this->mockProvider = Mockery::mock(ShippingProviderInterface::class);
        $this->mockProvider->shouldReceive('getName')->andReturn('test_provider');

        // Inject mock into service
        $this->service = new ShippingService($this->mockProvider);
    }

    public function test_quote_returns_enriched_response(): void
    {
        // Arrange
        $option = new ShippingOption(
            serviceCode: 'standard',
            serviceName: 'Standard Shipping',
            cost: 1500.0,
            estimatedDays: 5,
        );

        $response = new ShippingQuoteResponse(
            provider: 'test_provider',
            options: [$option],
        );

        $this->mockProvider
            ->shouldReceive('getQuote')
            ->once()
            ->andReturn($response);

        config(['services.andreani.origin_postal_code' => '1425']);

        // Act
        $result = $this->service->quote('1000', 1.5, 5000);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('free_threshold', $result);
        $this->assertEquals('test_provider', $result['provider']);
        $this->assertCount(1, $result['options']);
    }

    public function test_quote_returns_default_when_origin_not_configured(): void
    {
        // Arrange
        config(['services.andreani.origin_postal_code' => null]);

        // Act
        $result = $this->service->quote('1000', 1.5, 5000);

        // Assert
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('standard', $result['provider']);
        $this->assertArrayHasKey('options', $result);
    }

    public function test_create_shipment_updates_order_and_shipment(): void
    {
        // Arrange
        $order = Order::factory()->create();

        $creationResponse = new ShipmentCreationResponse(
            trackingNumber: 'TEST123456',
            labelUrl: 'https://example.com/label.pdf',
            estimatedDelivery: new \DateTimeImmutable('+5 days'),
            metadata: ['test' => 'data'],
        );

        $this->mockProvider
            ->shouldReceive('createShipment')
            ->once()
            ->with($order)
            ->andReturn($creationResponse);

        // Act
        $shipment = $this->service->createShipment($order);

        // Assert
        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals('TEST123456', $shipment->tracking_number);
        $this->assertEquals('https://example.com/label.pdf', $shipment->label_url);
        $this->assertNotNull($shipment->shipped_at);

        // Verify order was updated
        $order->refresh();
        $this->assertEquals('shipped', $order->status->value);
    }

    public function test_process_webhook_updates_shipment_status(): void
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TEST123456',
            'status' => 'shipped',
        ]);

        $webhookData = [
            'numeroAndreani' => 'TEST123456',
            'estado' => 'Entregado',
            'fecha' => '2024-01-15T14:30:00',
        ];

        // Act
        $this->service->processWebhook($webhookData);

        // Assert
        $shipment->refresh();
        $this->assertEquals('delivered', $shipment->status->value);
        $this->assertNotNull($shipment->delivered_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

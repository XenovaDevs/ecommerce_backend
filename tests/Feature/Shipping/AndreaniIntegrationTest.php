<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\ShippingStatus;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\AndreaniApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Andreani Integration Feature Tests.
 * Tests the complete flow of shipping operations through the API.
 *
 * @group shipping
 * @group andreani
 * @group integration
 */
class AndreaniIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user with shipment creation permission
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'is_admin' => true,
        ]);

        // Create customer user
        $this->customer = User::factory()->create([
            'email' => 'customer@test.com',
        ]);

        // Mock Andreani authentication
        $this->mockAndreaniAuth();
    }

    /**
     * Test: Can get shipping quote successfully.
     */
    public function test_can_get_shipping_quote_successfully(): void
    {
        // Mock Andreani quote response
        Http::fake([
            '*/envios/tarifa' => Http::response([
                'tarifas' => [
                    [
                        'servicio' => 'Estándar',
                        'precio' => 2500,
                        'plazoEntrega' => 5,
                        'modalidad' => 'Puerta a Puerta',
                    ],
                    [
                        'servicio' => 'Express',
                        'precio' => 4500,
                        'plazoEntrega' => 2,
                        'modalidad' => 'Puerta a Puerta',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/shipping/quote', [
            'postal_code' => '1426',
            'weight' => 2.5,
            'declared_value' => 15000,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'provider',
                    'options' => [
                        '*' => [
                            'service_code',
                            'service_name',
                            'cost',
                            'estimated_days',
                        ],
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'provider' => 'andreani',
                ],
            ]);
    }

    /**
     * Test: Returns fallback quote when Andreani API fails.
     */
    public function test_returns_fallback_quote_when_andreani_fails(): void
    {
        // Mock Andreani failure
        Http::fake([
            '*/envios/tarifa' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $response = $this->postJson('/api/v1/shipping/quote', [
            'postal_code' => '1426',
            'weight' => 1.0,
        ]);

        // Should still return 200 with fallback quote
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'provider',
                    'options',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'provider' => 'standard',
                ],
            ]);
    }

    /**
     * Test: Admin can create shipment for order.
     */
    public function test_admin_can_create_shipment_for_order(): void
    {
        $order = $this->createValidOrder();

        // Mock Andreani shipment creation
        Http::fake([
            '*/envios' => Http::response([
                'numeroAndreani' => 'AND123456789',
                'etiqueta' => 'https://andreani.com/labels/123456789.pdf',
                'fechaEstimadaEntrega' => '2026-02-10',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shipment' => [
                        'id',
                        'tracking_number',
                        'label_url',
                        'status',
                    ],
                    'order' => [
                        'id',
                        'order_number',
                        'status',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'shipment' => [
                        'tracking_number' => 'AND123456789',
                        'status' => ShippingStatus::SHIPPED->value,
                    ],
                ],
            ]);

        // Verify shipment was created in database
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'provider' => 'andreani',
            'tracking_number' => 'AND123456789',
            'status' => ShippingStatus::SHIPPED->value,
        ]);

        // Verify order status was updated
        $this->assertEquals(OrderStatus::SHIPPED, $order->fresh()->status);
    }

    /**
     * Test: Cannot create shipment for order without shipping address.
     */
    public function test_cannot_create_shipment_without_shipping_address(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'shipping_address_id' => null, // No shipping address
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test: Cannot create shipment for unpaid order.
     */
    public function test_cannot_create_shipment_for_unpaid_order(): void
    {
        $order = $this->createValidOrder();
        $order->update(['payment_status' => PaymentStatus::PENDING]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test: Can track shipment successfully.
     */
    public function test_can_track_shipment_successfully(): void
    {
        $trackingNumber = 'AND123456789';

        // Mock Andreani tracking response
        Http::fake([
            "*/envios/{$trackingNumber}/trazas" => Http::response([
                'trazas' => [
                    [
                        'estado' => 'Entregado',
                        'descripcion' => 'Paquete entregado correctamente',
                        'fecha' => '2026-02-10T14:30:00',
                        'sucursal' => 'Buenos Aires Centro',
                    ],
                    [
                        'estado' => 'En camino',
                        'descripcion' => 'Paquete en reparto',
                        'fecha' => '2026-02-10T08:00:00',
                        'sucursal' => 'Buenos Aires Centro',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/v1/shipping/track/{$trackingNumber}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tracking_number',
                    'status',
                    'events',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => $trackingNumber,
                ],
            ]);
    }

    /**
     * Test: Returns error when tracking number not found.
     */
    public function test_returns_error_when_tracking_number_not_found(): void
    {
        $trackingNumber = 'INVALID123';

        // Mock Andreani 404 response
        Http::fake([
            "*/envios/{$trackingNumber}/trazas" => Http::response([
                'error' => 'Tracking number not found',
            ], 404),
        ]);

        $response = $this->getJson("/api/v1/shipping/track/{$trackingNumber}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test: Guest cannot access admin shipment creation endpoint.
     */
    public function test_guest_cannot_access_admin_shipment_creation(): void
    {
        $order = $this->createValidOrder();

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertUnauthorized();
    }

    /**
     * Test: Regular user cannot create shipment (admin only).
     */
    public function test_regular_user_cannot_create_shipment(): void
    {
        $order = $this->createValidOrder();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertForbidden();
    }

    /**
     * Helper: Create a valid order ready for shipment.
     */
    private function createValidOrder(): Order
    {
        $product = Product::factory()->create([
            'price' => 10000,
            'stock' => 100,
        ]);

        $shippingAddress = OrderAddress::factory()->create([
            'postal_code' => '1426',
            'address' => 'Av. Cabildo 1234',
            'city' => 'Buenos Aires',
            'state' => 'CABA',
            'country' => 'Argentina',
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'shipping_address_id' => $shippingAddress->id,
            'total' => 12500,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10000,
        ]);

        return $order;
    }

    /**
     * Helper: Mock Andreani authentication.
     */
    private function mockAndreaniAuth(): void
    {
        Http::fake([
            '*/login' => Http::response([
                'token' => 'fake_token_123456789',
            ], 200),
        ]);
    }
}

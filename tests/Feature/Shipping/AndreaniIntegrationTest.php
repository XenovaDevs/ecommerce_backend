<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\ShippingStatus;
use App\Domain\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\AuthHelpers;

class AndreaniIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use AuthHelpers;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => UserRole::ADMIN,
        ]);

        $this->customer = User::factory()->create([
            'email' => 'customer@test.com',
        ]);

        config([
            'services.andreani.username' => 'TEST_USER',
            'services.andreani.password' => 'TEST_PASS',
            'services.andreani.contract_number' => 'TEST_CONTRACT',
            'services.andreani.origin_postal_code' => '1425',
        ]);

        $this->mockAndreaniAuth();
    }

    // ──────────────────────────────────────────────
    // Shipping Quote
    // ──────────────────────────────────────────────

    public function test_can_get_shipping_quote_successfully(): void
    {
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

    public function test_returns_fallback_quote_when_andreani_fails(): void
    {
        Http::fake([
            '*/envios/tarifa' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $response = $this->postJson('/api/v1/shipping/quote', [
            'postal_code' => '1426',
            'weight' => 1.0,
        ]);

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

    public function test_quote_validates_required_postal_code(): void
    {
        $response = $this->postJson('/api/v1/shipping/quote', [
            'weight' => 1.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_quote_validates_postal_code_max_length(): void
    {
        $response = $this->postJson('/api/v1/shipping/quote', [
            'postal_code' => '12345678901',
            'weight' => 1.0,
        ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────
    // Shipment Creation (Admin)
    // ──────────────────────────────────────────────

    public function test_admin_can_create_shipment_for_order(): void
    {
        $this->actingAsAdmin();
        $order = $this->createValidOrder();

        Http::fake([
            '*/envios' => Http::response([
                'numeroAndreani' => 'AND123456789',
                'urlEtiqueta' => 'https://andreani.com/labels/123456789.pdf',
                'fechaEntregaEstimada' => '2026-02-10',
            ], 200),
        ]);

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

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

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'provider' => 'andreani',
            'tracking_number' => 'AND123456789',
            'status' => ShippingStatus::SHIPPED->value,
        ]);

        $this->assertEquals(OrderStatus::SHIPPED, $order->fresh()->status);
    }

    public function test_cannot_create_shipment_without_shipping_address(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'status' => OrderStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'shipping_address_id' => null,
        ]);

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_create_shipment_for_unpaid_order(): void
    {
        $this->actingAsAdmin();
        $order = $this->createValidOrder();
        $order->update(['payment_status' => PaymentStatus::PENDING]);

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_create_duplicate_shipment_for_order(): void
    {
        $this->actingAsAdmin();
        $order = $this->createValidOrder();
        $order->update(['status' => OrderStatus::SHIPPED]);

        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'andreani',
            'tracking_number' => 'AND111111',
            'status' => ShippingStatus::SHIPPED,
        ]);

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    // ──────────────────────────────────────────────
    // Tracking
    // ──────────────────────────────────────────────

    public function test_can_track_shipment_successfully(): void
    {
        $trackingNumber = 'AND123456789';

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

    public function test_returns_error_when_tracking_number_not_found(): void
    {
        $trackingNumber = 'INVALID123';

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

    // ──────────────────────────────────────────────
    // Authorization
    // ──────────────────────────────────────────────

    public function test_guest_cannot_access_admin_shipment_creation(): void
    {
        $order = $this->createValidOrder();

        $response = $this->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_create_shipment(): void
    {
        $order = $this->createValidOrder();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/admin/shipments/orders/{$order->id}");

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

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
            'status' => OrderStatus::CONFIRMED,
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

    private function mockAndreaniAuth(): void
    {
        Http::fake([
            '*/login' => Http::response([
                'token' => 'fake_token_123456789',
            ], 200),
        ]);
    }
}

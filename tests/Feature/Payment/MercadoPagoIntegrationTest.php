<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\MercadoPagoService;
use App\Services\Payment\DTOs\PaymentPreferenceResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @ai-context Integration tests for Mercado Pago payment flow.
 *             Tests cover the complete payment lifecycle from preference creation
 *             to webhook processing.
 */
class MercadoPagoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test product
        $product = Product::factory()->create([
            'price' => 1000.00,
            'stock' => 10,
        ]);

        // Create test order
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
            'subtotal' => 1000.00,
            'tax' => 210.00,
            'total' => 1210.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000.00,
            'name' => $product->name,
        ]);

        // Set required config values
        config([
            'services.mercadopago.access_token' => 'TEST_ACCESS_TOKEN',
            'services.mercadopago.success_url' => 'http://localhost:3000/payment/success',
            'services.mercadopago.failure_url' => 'http://localhost:3000/payment/failure',
            'services.mercadopago.pending_url' => 'http://localhost:3000/payment/pending',
            'services.mercadopago.notification_url' => 'http://localhost:8000/api/v1/webhooks/mercadopago',
        ]);
    }

    /** @test */
    public function it_creates_payment_preference_successfully(): void
    {
        // Mock MercadoPagoService
        $mockResponse = new PaymentPreferenceResponse(
            preferenceId: 'test-preference-id',
            initPoint: 'https://www.mercadopago.com/checkout/test',
            sandboxInitPoint: 'https://sandbox.mercadopago.com/checkout/test'
        );

        $this->mock(MercadoPagoService::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('createPreference')
                ->once()
                ->andReturn($mockResponse);
        });

        // Make request
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments', [
                'order_id' => $this->order->id,
            ]);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'preference_id',
                    'init_point',
                    'sandbox_init_point',
                ],
            ]);

        // Assert payment was created
        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING->value,
            'amount' => $this->order->total,
        ]);
    }

    /** @test */
    public function it_prevents_creating_payment_for_already_paid_order(): void
    {
        // Mark order as already paid
        $this->order->update(['payment_status' => PaymentStatus::PAID]);

        // Attempt to create payment
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments', [
                'order_id' => $this->order->id,
            ]);

        // Assert error response
        $response->assertStatus(422)
            ->assertJsonFragment([
                'code' => 'ORDER_ALREADY_PROCESSED',
            ]);
    }

    /** @test */
    public function it_prevents_creating_payment_for_another_users_order(): void
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Attempt to create payment for other user's order
        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/payments', [
                'order_id' => $this->order->id,
            ]);

        // Assert not found
        $response->assertStatus(404);
    }

    /** @test */
    public function it_retrieves_payment_status(): void
    {
        // Create a payment
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

        // Request payment status
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}/status");

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $payment->id,
                    'order_id' => $this->order->id,
                    'status' => PaymentStatus::PENDING->value,
                    'amount' => $this->order->total,
                    'currency' => 'ARS',
                    'gateway' => 'mercado_pago',
                ],
            ]);
    }

    /** @test */
    public function it_syncs_payment_status_with_gateway_when_requested(): void
    {
        // Create a payment with Mercado Pago payment ID
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
            'metadata' => ['mp_payment_id' => '12345678'],
        ]);

        // Mock MercadoPagoService to return approved payment
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('getPayment')
                ->once()
                ->with('12345678')
                ->andReturn([
                    'id' => '12345678',
                    'status' => 'approved',
                    'status_detail' => 'accredited',
                    'external_reference' => '1',
                    'transaction_amount' => 1210.00,
                    'currency_id' => 'ARS',
                    'date_approved' => '2024-01-31 10:00:00',
                    'payer' => ['email' => 'test@example.com'],
                    'payment_method_id' => 'credit_card',
                    'payment_type_id' => 'credit_card',
                ]);

            $mock->shouldReceive('mapPaymentStatus')
                ->with('approved')
                ->andReturn('paid');
        });

        // Request payment status with sync
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}/status?sync=true");

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => PaymentStatus::PAID->value,
                ],
            ]);

        // Assert payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::PAID->value,
        ]);
    }

    /** @test */
    public function it_processes_mercado_pago_webhook_successfully(): void
    {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Create a payment
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

        // Mock MercadoPagoService
        $this->mock(MercadoPagoService::class, function ($mock) use ($payment) {
            $mock->shouldReceive('validateWebhookSignature')
                ->andReturn(true);

            $mock->shouldReceive('getPayment')
                ->once()
                ->with('12345678')
                ->andReturn([
                    'id' => '12345678',
                    'status' => 'approved',
                    'status_detail' => 'accredited',
                    'external_reference' => (string) $payment->id,
                    'transaction_amount' => 1210.00,
                    'currency_id' => 'ARS',
                    'date_approved' => '2024-01-31 10:00:00',
                    'payer' => ['email' => 'test@example.com'],
                    'payment_method_id' => 'credit_card',
                    'payment_type_id' => 'credit_card',
                ]);

            $mock->shouldReceive('mapPaymentStatus')
                ->with('approved')
                ->andReturn('paid');
        });

        // Send webhook
        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => [
                'id' => '12345678',
            ],
        ]);

        // Assert webhook accepted
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Assert payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::PAID->value,
        ]);

        // Assert order was marked as paid
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => PaymentStatus::PAID->value,
        ]);
    }
}

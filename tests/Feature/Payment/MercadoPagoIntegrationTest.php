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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MercadoPagoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000.00,
            'stock' => 10,
        ]);

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

        config([
            'services.mercadopago.access_token' => 'TEST_ACCESS_TOKEN',
            'services.mercadopago.success_url' => 'http://localhost:3000/payment/success',
            'services.mercadopago.failure_url' => 'http://localhost:3000/payment/failure',
            'services.mercadopago.pending_url' => 'http://localhost:3000/payment/pending',
            'services.mercadopago.notification_url' => 'http://localhost:8000/api/v1/webhooks/mercadopago',
            'services.andreani.username' => 'TEST_USER',
            'services.andreani.password' => 'TEST_PASS',
            'services.andreani.contract_number' => 'TEST_CONTRACT',
            'services.andreani.origin_postal_code' => '1425',
        ]);
    }

    // ──────────────────────────────────────────────
    // Payment Preference Creation
    // ──────────────────────────────────────────────

    public function test_creates_payment_preference_successfully(): void
    {
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/create', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'preference_id',
                    'init_point',
                    'sandbox_init_point',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING->value,
            'amount' => $this->order->total,
        ]);
    }

    public function test_prevents_creating_payment_for_already_paid_order(): void
    {
        $this->order->update(['payment_status' => PaymentStatus::PAID]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/create', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonFragment([
                'code' => 'ORDER_ALREADY_PROCESSED',
            ]);
    }

    public function test_prevents_creating_payment_for_another_users_order(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/payments/create', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_prevents_creating_payment_without_order_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/create', []);

        $response->assertStatus(422);
    }

    public function test_prevents_creating_payment_for_nonexistent_order(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/create', [
                'order_id' => 99999,
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_create_payment(): void
    {
        $response = $this->postJson('/api/v1/payments/create', [
            'order_id' => $this->order->id,
        ]);

        $response->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    // Payment Status
    // ──────────────────────────────────────────────

    public function test_retrieves_payment_status(): void
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}/status");

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

    public function test_syncs_payment_status_with_gateway_when_requested(): void
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
            'external_id' => 'pref-123',
            'metadata' => ['mp_payment_id' => '12345678'],
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($payment) {
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

        Event::fake();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}/status?sync=true");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => PaymentStatus::PAID->value,
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::PAID->value,
        ]);
    }

    // ──────────────────────────────────────────────
    // Webhook Processing
    // ──────────────────────────────────────────────

    public function test_processes_webhook_with_approved_payment(): void
    {
        Log::spy();
        Event::fake();

        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

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

        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '12345678'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => PaymentStatus::PAID->value,
        ]);
    }

    public function test_processes_webhook_with_rejected_payment(): void
    {
        Log::spy();

        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($payment) {
            $mock->shouldReceive('validateWebhookSignature')
                ->andReturn(true);

            $mock->shouldReceive('getPayment')
                ->once()
                ->with('99999')
                ->andReturn([
                    'id' => '99999',
                    'status' => 'rejected',
                    'status_detail' => 'cc_rejected_insufficient_amount',
                    'external_reference' => (string) $payment->id,
                    'transaction_amount' => 1210.00,
                    'currency_id' => 'ARS',
                    'date_approved' => null,
                    'payer' => ['email' => 'test@example.com'],
                    'payment_method_id' => 'credit_card',
                    'payment_type_id' => 'credit_card',
                ]);

            $mock->shouldReceive('mapPaymentStatus')
                ->with('rejected')
                ->andReturn('failed');
        });

        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '99999'],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::FAILED->value,
        ]);
    }

    public function test_webhook_ignores_non_payment_type(): void
    {
        Log::spy();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('validateWebhookSignature')
                ->andReturn(true);

            $mock->shouldNotReceive('getPayment');
        });

        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'merchant_order',
            'action' => 'merchant_order.updated',
            'data' => ['id' => '12345678'],
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        Log::spy();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('validateWebhookSignature')
                ->andReturn(false);
        });

        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '12345678'],
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_idempotency_does_not_double_process(): void
    {
        Log::spy();
        Event::fake();

        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PAID,
            'amount' => $this->order->total,
            'currency' => 'ARS',
        ]);

        $this->order->update(['payment_status' => PaymentStatus::PAID]);

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

        $response = $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '12345678'],
        ]);

        $response->assertStatus(200);

        // Order should still be paid, no duplicate event
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => PaymentStatus::PAID->value,
        ]);
    }
}

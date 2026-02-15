<?php

namespace Tests\Unit\Services\Payment;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\MercadoPagoService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentServiceLatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_payment_does_not_reactivate_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CANCELLED,
            'payment_status' => PaymentStatus::CANCELLED,
            'paid_at' => null,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::PENDING,
        ]);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoService
            ->shouldReceive('getPayment')
            ->once()
            ->with('mp-payment-123')
            ->andReturn([
                'id' => 'mp-payment-123',
                'external_reference' => (string) $payment->id,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'payment_method_id' => 'visa',
                'payment_type_id' => 'credit_card',
                'date_approved' => now()->toIso8601String(),
            ]);
        $mercadoPagoService
            ->shouldReceive('mapPaymentStatus')
            ->once()
            ->with('approved')
            ->andReturn('paid');

        $service = new PaymentService($mercadoPagoService);
        $service->processWebhook([
            'type' => 'payment',
            'data' => [
                'id' => 'mp-payment-123',
            ],
        ]);

        $order->refresh();

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'Late payment received',
        ]);
    }
}


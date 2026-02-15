<?php

namespace Tests\Feature\Order;

use App\Jobs\SendOrderConfirmation;
use App\Jobs\SendPendingPaymentReminder;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Services\Payment\DTOs\PaymentPreferenceResponse;
use App\Services\Payment\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_persists_contact_email_and_dispatches_notifications(): void
    {
        Bus::fake([SendOrderConfirmation::class, SendPendingPaymentReminder::class]);

        $sessionId = 'guest-session-1';
        $product = Product::factory()->create([
            'stock' => 20,
            'price' => 199.99,
        ]);

        $this->withHeader('X-Session-ID', $sessionId)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertSuccessful();

        $response = $this->withHeader('X-Session-ID', $sessionId)
            ->postJson('/api/v1/checkout/guest/process', [
                'shipping_address' => [
                    'name' => 'Guest User',
                    'email' => 'guest@example.com',
                    'address' => 'Av. Siempre Viva 123',
                    'city' => 'Buenos Aires',
                    'state' => 'CABA',
                    'postal_code' => '1000',
                    'country' => 'AR',
                ],
                'shipping_cost' => 0,
                'payment_method' => 'mercadopago',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order',
                    'payment_url',
                ],
            ]);

        $this->assertDatabaseHas('order_addresses', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
        ]);

        Bus::assertDispatched(SendOrderConfirmation::class);
        Bus::assertDispatched(SendPendingPaymentReminder::class);
    }

    public function test_guest_can_recreate_payment_preference_for_pending_order(): void
    {
        $mercadoPagoMock = Mockery::mock(MercadoPagoService::class);
        $mercadoPagoMock
            ->shouldReceive('createPreference')
            ->once()
            ->andReturn(new PaymentPreferenceResponse(
                preferenceId: 'PREF-TEST-001',
                initPoint: 'https://www.mercadopago.com/checkout/test',
                sandboxInitPoint: 'https://sandbox.mercadopago.com/checkout/test'
            ));

        $this->app->instance(MercadoPagoService::class, $mercadoPagoMock);

        $shippingAddress = OrderAddress::factory()->shipping()->create([
            'name' => 'Guest Buyer',
            'email' => 'guest.buyer@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'shipping_address_id' => $shippingAddress->id,
            'billing_address_id' => $shippingAddress->id,
        ]);

        $product = Product::factory()->create([
            'price' => 100,
            'stock' => 10,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
        ]);

        $response = $this->postJson('/api/v1/checkout/guest/payment-preference', [
            'order_number' => $order->order_number,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preference_id', 'PREF-TEST-001')
            ->assertJsonPath('data.init_point', 'https://sandbox.mercadopago.com/checkout/test');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    }
}


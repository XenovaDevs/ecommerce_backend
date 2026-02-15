<?php

namespace Tests\Feature\Order;

use App\Jobs\SendOrderPaymentExpiredNotification;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_overdue_unpaid_orders_and_restores_stock(): void
    {
        Bus::fake([SendOrderPaymentExpiredNotification::class]);

        $shippingAddress = OrderAddress::factory()->shipping()->create([
            'email' => 'guest@example.com',
        ]);

        $product = Product::factory()->create([
            'stock' => 8,
            'track_stock' => true,
            'price' => 100,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address_id' => $shippingAddress->id,
            'billing_address_id' => $shippingAddress->id,
            'created_at' => now()->subHours(25),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'price' => 100,
            'total' => 200,
        ]);

        $this->artisan('orders:expire-unpaid')
            ->assertExitCode(0);

        $order->refresh();
        $product->refresh();

        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('cancelled', $order->payment_status->value);
        $this->assertSame(10, $product->stock);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'Cancelled',
        ]);

        Bus::assertDispatched(SendOrderPaymentExpiredNotification::class);
    }
}


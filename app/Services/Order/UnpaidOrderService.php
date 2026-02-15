<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Jobs\SendOrderPaymentExpiredNotification;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnpaidOrderService
{
    /**
     * Cancel unpaid pending orders older than configured cutoff.
     *
     * @return int Number of expired orders processed.
     */
    public function expireOverdueUnpaidOrders(int $expirationHours): int
    {
        $cutoff = now()->subHours($expirationHours);
        $orders = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('payment_status', PaymentStatus::PENDING)
            ->where('created_at', '<=', $cutoff)
            ->with(['items.product', 'shippingAddress', 'user'])
            ->get();

        $processed = 0;

        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$processed, $expirationHours): void {
                $order->refresh();

                if (
                    $order->status !== OrderStatus::PENDING
                    || $order->payment_status !== PaymentStatus::PENDING
                ) {
                    return;
                }

                $order->loadMissing(['items.product', 'shippingAddress', 'user']);

                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increaseStock($item->quantity, $item->variant_id);
                    }
                }

                $order->update([
                    'payment_status' => PaymentStatus::CANCELLED,
                ]);
                $order->updateStatus(
                    OrderStatus::CANCELLED,
                    "Cancelled automatically due to unpaid payment timeout ({$expirationHours}h)"
                );

                $processed++;

                DB::afterCommit(static function () use ($order): void {
                    SendOrderPaymentExpiredNotification::dispatch($order);
                });

                Log::info('Order cancelled due to unpaid timeout', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            });
        }

        return $processed;
    }
}


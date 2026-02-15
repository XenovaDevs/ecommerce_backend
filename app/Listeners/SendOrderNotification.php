<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendOrderConfirmation;
use App\Jobs\SendPendingPaymentReminder;

/**
 * @ai-context SendOrderNotification listener handles order creation events.
 */
class SendOrderNotification
{
    public function handle(OrderCreated $event): void
    {
        SendOrderConfirmation::dispatch($event->order);
        SendPendingPaymentReminder::dispatch($event->order)
            ->delay(now()->addHours((int) config('checkout.pending_payment_reminder_hours', 12)));
    }
}

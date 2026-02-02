<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendOrderConfirmation;

/**
 * @ai-context SendOrderNotification listener handles order creation events.
 */
class SendOrderNotification
{
    public function handle(OrderCreated $event): void
    {
        SendOrderConfirmation::dispatch($event->order);
    }
}

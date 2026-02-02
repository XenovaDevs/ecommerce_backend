<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Support\Constants\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * @ai-context SendOrderConfirmation sends order confirmation email.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Order $order
    ) {
        $this->onQueue(QueueNames::EMAILS);
    }

    public function handle(): void
    {
        // Mail::to($this->order->user->email)->send(new OrderConfirmationMail($this->order));
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Mail\OrderPendingPaymentReminderMail;
use App\Models\Order;
use App\Support\Constants\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPendingPaymentReminder implements ShouldQueue
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
        $order = $this->order->fresh(['user', 'shippingAddress']);

        if (!$order) {
            return;
        }

        if ($order->status !== OrderStatus::PENDING || $order->payment_status !== PaymentStatus::PENDING) {
            return;
        }

        $recipientEmail = $order->user?->email ?? $order->shippingAddress?->email;

        if (!$recipientEmail) {
            return;
        }

        $expirationHours = (int) config('checkout.pending_payment_expiration_hours', 24);

        Mail::to($recipientEmail)->send(
            new OrderPendingPaymentReminderMail($order, $expirationHours)
        );
    }
}


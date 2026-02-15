<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPendingPaymentCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public int $expirationHours
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Recibimos tu pedido {$this->order->order_number}: pendiente de pago"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.pending-created'
        );
    }
}


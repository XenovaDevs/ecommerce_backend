<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPaymentExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public int $expirationHours
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pedido {$this->order->order_number} cancelado por falta de pago"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.payment-expired'
        );
    }
}


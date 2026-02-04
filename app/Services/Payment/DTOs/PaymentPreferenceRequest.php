<?php

declare(strict_types=1);

namespace App\Services\Payment\DTOs;

use App\Models\Order;
use App\Models\User;
use App\Models\Setting;

/**
 * @ai-context PaymentPreferenceRequest encapsulates the data required to create
 *             a payment preference. Following DTO pattern, this class is immutable
 *             and serves as a data container with validation.
 */
final readonly class PaymentPreferenceRequest
{
    /**
     * @param array<int, array{id: string, title: string, description: string, quantity: int, unit_price: float, currency_id: string}> $items
     * @param array{name: string, email: string} $payer
     * @param array{success: string, failure: string, pending: string} $backUrls
     * @param string $externalReference
     * @param string|null $notificationUrl
     * @param string|null $statementDescriptor
     */
    public function __construct(
        public array $items,
        public array $payer,
        public array $backUrls,
        public string $externalReference,
        public ?string $notificationUrl = null,
        public ?string $statementDescriptor = null,
        public string $autoReturn = 'approved'
    ) {
        $this->validate();
    }

    /**
     * Create PaymentPreferenceRequest from Order and User models.
     *
     * @param Order $order
     * @param User $user
     * @param int $paymentId
     * @return self
     */
    public static function fromOrder(Order $order, User $user, int $paymentId): self
    {
        $items = $order->items->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'title' => $item->name,
            'description' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->price,
            'currency_id' => Setting::get('currency', 'ARS'),
        ])->toArray();

        $payer = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $backUrls = [
            'success' => config('services.mercadopago.success_url'),
            'failure' => config('services.mercadopago.failure_url'),
            'pending' => config('services.mercadopago.pending_url'),
        ];

        return new self(
            items: $items,
            payer: $payer,
            backUrls: $backUrls,
            externalReference: (string) $paymentId,
            notificationUrl: config('services.mercadopago.notification_url'),
            statementDescriptor: config('app.name')
        );
    }

    /**
     * Convert to array for Mercado Pago API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'items' => $this->items,
            'payer' => $this->payer,
            'back_urls' => $this->backUrls,
            'auto_return' => $this->autoReturn,
            'external_reference' => $this->externalReference,
            'notification_url' => $this->notificationUrl,
            'statement_descriptor' => $this->statementDescriptor,
        ], fn ($value) => $value !== null);
    }

    /**
     * Validate the DTO data.
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty($this->items)) {
            throw new \InvalidArgumentException('Items cannot be empty');
        }

        if (empty($this->payer['name']) || empty($this->payer['email'])) {
            throw new \InvalidArgumentException('Payer name and email are required');
        }

        if (!filter_var($this->payer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid payer email');
        }

        if (empty($this->backUrls['success']) || empty($this->backUrls['failure']) || empty($this->backUrls['pending'])) {
            throw new \InvalidArgumentException('All back URLs (success, failure, pending) are required');
        }

        if (empty($this->externalReference)) {
            throw new \InvalidArgumentException('External reference is required');
        }

        foreach ($this->items as $item) {
            if (!isset($item['id'], $item['title'], $item['quantity'], $item['unit_price'], $item['currency_id'])) {
                throw new \InvalidArgumentException('Invalid item structure');
            }

            if ($item['quantity'] <= 0) {
                throw new \InvalidArgumentException('Item quantity must be greater than 0');
            }

            if ($item['unit_price'] <= 0) {
                throw new \InvalidArgumentException('Item unit price must be greater than 0');
            }
        }
    }
}

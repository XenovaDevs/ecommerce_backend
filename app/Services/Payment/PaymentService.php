<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Exceptions\Domain\InvalidOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payment\DTOs\PaymentPreferenceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @ai-context PaymentService handles payment processing orchestration.
 *             Following Single Responsibility Principle, this service coordinates
 *             payment operations but delegates gateway-specific logic to specialized services.
 *             It manages the Payment domain model and ensures transactional integrity.
 */
class PaymentService
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService
    ) {}

    /**
     * Create a payment preference for an order.
     * This method orchestrates the payment creation process, validates the order,
     * creates the payment record, and delegates to the payment gateway service.
     *
     * @param User|null $user
     * @param int $orderId
     * @param array{name: string, email: string}|null $guestPayer
     * @return array<string, mixed>
     * @throws EntityNotFoundException
     * @throws InvalidOperationException
     */
    public function createPaymentPreference(?User $user, int $orderId, ?array $guestPayer = null): array
    {
        $order = $this->findOrderForPayment($user, $orderId);
        $this->validateOrderCanBePaid($order);

        return DB::transaction(function () use ($order, $user, $guestPayer) {
            $payment = $this->createPaymentRecord($order);

            try {
                // Build preference request using DTO
                if ($user) {
                    $preferenceRequest = PaymentPreferenceRequest::fromOrder($order, $user, $payment->id);
                } elseif (!empty($guestPayer['name']) && !empty($guestPayer['email'])) {
                    $preferenceRequest = PaymentPreferenceRequest::fromOrderWithPayer(
                        $order,
                        $guestPayer['name'],
                        $guestPayer['email'],
                        $payment->id
                    );
                } else {
                    throw new InvalidOperationException(
                        'Guest checkout requires payer name and email',
                        'PAYER_INFO_REQUIRED'
                    );
                }

                // Create preference in Mercado Pago
                $preferenceResponse = $this->mercadoPagoService->createPreference($preferenceRequest);

                // Update payment with preference data
                $payment->update([
                    'external_id' => $preferenceResponse->preferenceId,
                    'metadata' => ['preference_id' => $preferenceResponse->preferenceId],
                ]);

                Log::info('Payment preference created successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'preference_id' => $preferenceResponse->preferenceId,
                ]);

                return [
                    'payment_id' => $payment->id,
                    'preference_id' => $preferenceResponse->preferenceId,
                    'init_point' => $preferenceResponse->getInitPoint(),
                    'sandbox_init_point' => $preferenceResponse->sandboxInitPoint,
                ];
            } catch (InvalidOperationException $e) {
                $payment->markAsFailed($e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get the current status of a payment.
     * This method retrieves payment information and optionally syncs with the gateway.
     *
     * @param int $paymentId
     * @param bool $syncWithGateway Whether to fetch fresh data from payment gateway
     * @return array<string, mixed>
     * @throws EntityNotFoundException
     */
    public function getPaymentStatus(int $paymentId, bool $syncWithGateway = false): array
    {
        $payment = Payment::with('order')->find($paymentId);

        if (!$payment) {
            throw new EntityNotFoundException('Payment', $paymentId);
        }

        // Optionally sync with gateway to get the latest status
        if ($syncWithGateway && $payment->external_id) {
            $this->syncPaymentWithGateway($payment);
            $payment->refresh();
        }

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            'external_id' => $payment->external_id,
            'created_at' => $payment->created_at->toIso8601String(),
            'updated_at' => $payment->updated_at->toIso8601String(),
        ];
    }

    /**
     * Process webhook notification from payment gateway.
     * This method handles incoming webhook events and updates payment/order status accordingly.
     *
     * @param array<string, mixed> $webhookData
     * @return void
     */
    public function processWebhook(array $webhookData): void
    {
        Log::info('Processing Mercado Pago webhook', [
            'type' => $webhookData['type'] ?? 'unknown',
            'action' => $webhookData['action'] ?? 'unknown',
        ]);

        // Only process payment-related webhooks
        if (!isset($webhookData['type']) || $webhookData['type'] !== 'payment') {
            Log::info('Ignoring non-payment webhook', [
                'type' => $webhookData['type'] ?? 'unknown',
            ]);
            return;
        }

        // Extract payment ID from webhook data
        $paymentId = $webhookData['data']['id'] ?? null;

        if (!$paymentId) {
            Log::warning('Webhook missing payment ID', ['data' => $webhookData]);
            return;
        }

        try {
            $this->processPaymentUpdate((string) $paymentId);
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process a payment update from Mercado Pago.
     * Fetches the latest payment information and updates local records.
     *
     * @param string $mercadoPagoPaymentId
     * @return void
     */
    private function processPaymentUpdate(string $mercadoPagoPaymentId): void
    {
        $paymentInfo = $this->mercadoPagoService->getPayment($mercadoPagoPaymentId);

        if (!$paymentInfo) {
            Log::warning('Could not fetch payment info from Mercado Pago', [
                'mp_payment_id' => $mercadoPagoPaymentId,
            ]);
            return;
        }

        // Find the payment by external reference (our payment ID)
        $payment = Payment::where('id', $paymentInfo['external_reference'])
            ->orWhere('external_id', $paymentInfo['id'])
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for Mercado Pago notification', [
                'mp_payment_id' => $mercadoPagoPaymentId,
                'external_reference' => $paymentInfo['external_reference'] ?? null,
            ]);
            return;
        }

        DB::transaction(function () use ($payment, $paymentInfo, $mercadoPagoPaymentId) {
            $this->updatePaymentFromGatewayData($payment, $paymentInfo, $mercadoPagoPaymentId);
        });
    }

    /**
     * Update payment and order status based on gateway data.
     *
     * @param Payment $payment
     * @param array<string, mixed> $paymentInfo
     * @param string $mercadoPagoPaymentId
     * @return void
     */
    private function updatePaymentFromGatewayData(
        Payment $payment,
        array $paymentInfo,
        string $mercadoPagoPaymentId
    ): void {
        $newStatus = PaymentStatus::from(
            $this->mercadoPagoService->mapPaymentStatus($paymentInfo['status'])
        );

        $oldStatus = $payment->status;

        // Update payment record
        $payment->update([
            'status' => $newStatus,
            'metadata' => array_merge($payment->metadata ?? [], [
                'mp_payment_id' => $mercadoPagoPaymentId,
                'mp_status' => $paymentInfo['status'],
                'mp_status_detail' => $paymentInfo['status_detail'],
                'payment_method' => $paymentInfo['payment_method_id'],
                'payment_type' => $paymentInfo['payment_type_id'],
                'date_approved' => $paymentInfo['date_approved'],
            ]),
        ]);

        Log::info('Payment status updated', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
        ]);

        // Update order based on payment status
        if ($newStatus === PaymentStatus::PAID && $oldStatus !== PaymentStatus::PAID) {
            if ($payment->order->status === OrderStatus::CANCELLED) {
                $payment->order->update([
                    'payment_status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                ]);
                $payment->order->addStatusHistory(
                    'Late payment received',
                    'Payment approved after order cancellation. Manual review required.'
                );

                Log::warning('Late payment received for cancelled order', [
                    'order_id' => $payment->order_id,
                    'payment_id' => $payment->id,
                    'mp_payment_id' => $mercadoPagoPaymentId,
                ]);

                return;
            }

            $payment->order->markAsPaid($mercadoPagoPaymentId);
            OrderPaid::dispatch($payment->order, $mercadoPagoPaymentId);

            Log::info('Order marked as paid', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
        } elseif ($newStatus === PaymentStatus::FAILED) {
            $payment->order->markPaymentFailed($paymentInfo['status_detail'] ?? 'Payment failed');

            Log::info('Order payment marked as failed', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'reason' => $paymentInfo['status_detail'] ?? 'Unknown',
            ]);
        }
    }

    /**
     * Sync payment status with the payment gateway.
     *
     * @param Payment $payment
     * @return void
     */
    private function syncPaymentWithGateway(Payment $payment): void
    {
        $metadata = $payment->metadata ?? [];
        $mercadoPagoPaymentId = $metadata['mp_payment_id'] ?? null;

        if (!$mercadoPagoPaymentId) {
            Log::info('Cannot sync payment - no Mercado Pago payment ID', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $paymentInfo = $this->mercadoPagoService->getPayment($mercadoPagoPaymentId);

        if ($paymentInfo) {
            $this->updatePaymentFromGatewayData($payment, $paymentInfo, $mercadoPagoPaymentId);
        }
    }

    /**
     * Find and validate order for payment processing.
     *
     * @param User|null $user
     * @param int $orderId
     * @return Order
     * @throws EntityNotFoundException
     */
    private function findOrderForPayment(?User $user, int $orderId): Order
    {
        $query = Order::where('id', $orderId);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id');
        }

        $order = $query
            ->with(['items', 'shippingAddress'])
            ->first();

        if (!$order) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        return $order;
    }

    /**
     * Validate that an order can be paid.
     *
     * @param Order $order
     * @return void
     * @throws InvalidOperationException
     */
    private function validateOrderCanBePaid(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::PENDING) {
            throw new InvalidOperationException(
                'This order cannot be paid. Current status: ' . $order->payment_status->label(),
                'ORDER_ALREADY_PROCESSED'
            );
        }
    }

    /**
     * Create a payment record for the order.
     *
     * @param Order $order
     * @return Payment
     */
    private function createPaymentRecord(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercado_pago',
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total,
            'currency' => Setting::get('currency', 'ARS'),
        ]);
    }

}

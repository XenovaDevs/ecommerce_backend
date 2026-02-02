<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * @ai-context Payment status enum for payment workflow.
 *             Defines the possible states of a payment.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case APPROVED = 'approved';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::PAID => 'Paid',
            self::APPROVED => 'Approved',
            self::FAILED => 'Failed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::PAID, self::APPROVED], true);
    }

    /**
     * Check if payment is in a final failed state.
     */
    public function isFailed(): bool
    {
        return in_array($this, [self::FAILED, self::REJECTED, self::CANCELLED], true);
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return in_array($this, [self::PAID, self::APPROVED], true);
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

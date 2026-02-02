<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * @ai-context Payment method enum.
 *             Defines the available payment methods.
 */
enum PaymentMethod: string
{
    case MERCADO_PAGO = 'mercado_pago';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';

    /**
     * Get human-readable label for the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::MERCADO_PAGO => 'Mercado Pago',
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CASH => 'Cash',
        };
    }

    /**
     * Check if method requires online processing.
     */
    public function requiresOnlineProcessing(): bool
    {
        return in_array($this, [
            self::MERCADO_PAGO,
            self::CREDIT_CARD,
            self::DEBIT_CARD,
        ], true);
    }

    /**
     * Get all method values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

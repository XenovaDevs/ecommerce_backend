<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Pending Policies
    |--------------------------------------------------------------------------
    |
    | Hours before an unpaid order is automatically cancelled and the reminder
    | timing used for pending payment emails.
    |
    */
    'pending_payment_expiration_hours' => (int) env('CHECKOUT_PENDING_PAYMENT_EXPIRATION_HOURS', 24),
    'pending_payment_reminder_hours' => (int) env('CHECKOUT_PENDING_PAYMENT_REMINDER_HOURS', 12),
];


<?php

use App\Services\Order\UnpaidOrderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:expire-unpaid {--hours= : Override expiration window in hours}', function (): void {
    $unpaidOrderService = app(UnpaidOrderService::class);
    $configuredHours = (int) config('checkout.pending_payment_expiration_hours', 24);
    $expirationHours = (int) ($this->option('hours') ?: $configuredHours);

    $expiredCount = $unpaidOrderService->expireOverdueUnpaidOrders($expirationHours);

    $this->info("Expired {$expiredCount} unpaid order(s) using {$expirationHours}h window.");
})->purpose('Cancel overdue unpaid orders and restore stock');

Schedule::command('orders:expire-unpaid')->hourly();

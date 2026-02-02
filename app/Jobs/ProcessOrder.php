<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Order\OrderService;
use App\Support\Constants\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @ai-context ProcessOrder job handles order processing workflow.
 */
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Order $order
    ) {
        $this->onQueue(QueueNames::ORDERS);
    }

    public function handle(OrderService $orderService): void
    {
        // Process order logic
        $orderService->processOrder($this->order);
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failed job
        \Log::error('Order processing failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

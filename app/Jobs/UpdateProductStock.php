<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Support\Constants\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @ai-context UpdateProductStock updates product stock.
 */
class UpdateProductStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $productId,
        public int $quantity,
        public string $operation = 'decrement'
    ) {
        $this->onQueue(QueueNames::STOCK);
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if ($product && $product->track_stock) {
            if ($this->operation === 'decrement') {
                $product->decrement('stock', $this->quantity);
            } else {
                $product->increment('stock', $this->quantity);
            }
        }
    }
}

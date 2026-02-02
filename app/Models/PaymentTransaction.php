<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context PaymentTransaction model for payment transaction records.
 */
class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'type',
        'status',
        'amount',
        'external_reference',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'response' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

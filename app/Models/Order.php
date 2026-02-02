<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @ai-context Order model for customer orders.
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'payment_status',
        'subtotal',
        'shipping_cost',
        'tax',
        'discount',
        'total',
        'notes',
        'shipping_address_id',
        'billing_address_id',
        'paid_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'float',
            'shipping_cost' => 'float',
            'tax' => 'float',
            'discount' => 'float',
            'total' => 'float',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));
        return "{$prefix}-{$timestamp}-{$random}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(OrderAddress::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(OrderAddress::class, 'billing_address_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function markAsPaid(?string $transactionId = null): void
    {
        $this->update([
            'payment_status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);
        $this->addStatusHistory('Payment received', $transactionId);
    }

    public function markPaymentFailed(?string $reason = null): void
    {
        $this->update([
            'payment_status' => PaymentStatus::FAILED,
        ]);
        $this->addStatusHistory('Payment failed', $reason);
    }

    public function updateStatus(OrderStatus $status, ?string $notes = null, ?int $changedBy = null): void
    {
        $this->update(['status' => $status]);
        $this->addStatusHistory($status->label(), $notes, $changedBy);

        if ($status === OrderStatus::SHIPPED) {
            $this->update(['shipped_at' => now()]);
        } elseif ($status === OrderStatus::DELIVERED) {
            $this->update(['delivered_at' => now()]);
        }
    }

    public function addStatusHistory(string $status, ?string $notes = null, ?int $changedBy = null): void
    {
        $this->statusHistory()->create([
            'status' => $status,
            'notes' => $notes,
            'changed_by' => $changedBy,
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
        ]);
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PAID);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enums\ShippingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context Shipment model for order shipments.
 */
class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'tracking_number',
        'status',
        'label_url',
        'estimated_delivery',
        'shipped_at',
        'delivered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShippingStatus::class,
            'estimated_delivery' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === ShippingStatus::DELIVERED;
    }

    public function isInTransit(): bool
    {
        return $this->status === ShippingStatus::IN_TRANSIT;
    }

    public function markAsShipped(string $trackingNumber, ?string $labelUrl = null): void
    {
        $this->update([
            'status' => ShippingStatus::SHIPPED,
            'tracking_number' => $trackingNumber,
            'label_url' => $labelUrl,
            'shipped_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => ShippingStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function getTrackingUrlAttribute(): ?string
    {
        if (!$this->tracking_number) {
            return null;
        }

        return match ($this->provider) {
            'andreani' => "https://www.andreani.com/#!/informacionEnvio/{$this->tracking_number}",
            'oca' => "https://www.oca.com.ar/Busquedas/EnviosWeb?NumEnvio={$this->tracking_number}",
            default => null,
        };
    }
}

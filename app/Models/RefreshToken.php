<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @ai-context Model for storing refresh tokens.
 *             Used for long-lived authentication with token refresh capability.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property bool $revoked
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'revoked',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return !$this->revoked && $this->expires_at->isFuture();
    }

    /**
     * Revoke the token.
     */
    public function revoke(): void
    {
        $this->update(['revoked' => true]);
    }
}

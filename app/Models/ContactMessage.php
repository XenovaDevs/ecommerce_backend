<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enums\ContactMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @ai-context ContactMessage model for customer support and contact messages.
 *             Allows customers to send inquiries that can be managed by admins.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property string $status
 * @property string|null $admin_reply
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ContactMessage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_reply',
        'reply',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Check if the message is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ContactMessageStatus::PENDING;
    }

    /**
     * Check if the message has been replied to.
     */
    public function isReplied(): bool
    {
        return $this->status === ContactMessageStatus::REPLIED;
    }

    /**
     * Check if the message is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === ContactMessageStatus::CLOSED;
    }

    /**
     * Mark the message as replied.
     */
    public function markAsReplied(): void
    {
        $this->update(['status' => ContactMessageStatus::REPLIED]);
    }

    /**
     * Mark the message as closed.
     */
    public function markAsClosed(): void
    {
        $this->update(['status' => ContactMessageStatus::CLOSED]);
    }

    /**
     * Set the admin reply for this message.
     */
    public function setReply(string $reply): void
    {
        $this->update([
            'admin_reply' => $reply,
            'status' => ContactMessageStatus::REPLIED,
        ]);
    }
}

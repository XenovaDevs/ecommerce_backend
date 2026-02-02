<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * @ai-context Contact message status enum for contact workflow.
 *             Defines the possible states of a contact message.
 */
enum ContactMessageStatus: string
{
    case PENDING = 'pending';
    case REPLIED = 'replied';
    case CLOSED = 'closed';

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::REPLIED => 'Replied',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if status is final.
     */
    public function isFinal(): bool
    {
        return $this === self::CLOSED;
    }

    /**
     * Check if status allows replies.
     */
    public function allowsReply(): bool
    {
        return in_array($this, [self::PENDING, self::REPLIED], true);
    }
}

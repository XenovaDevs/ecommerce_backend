<?php

declare(strict_types=1);

namespace App\Support\Constants;

/**
 * @ai-context Centralized queue names for all background jobs.
 *             All queue names MUST be defined here for consistency.
 * @ai-dependencies Used by Jobs when dispatching to specific queues
 * @ai-flow Jobs specify their queue using these constants
 */
final class QueueNames
{
    /**
     * Default queue for general jobs.
     */
    public const DEFAULT = 'default';

    /**
     * High priority queue for critical operations.
     */
    public const HIGH = 'high';

    /**
     * Low priority queue for non-urgent tasks.
     */
    public const LOW = 'low';

    /**
     * Payment processing queue.
     * Separate queue to ensure payment jobs are processed reliably.
     */
    public const PAYMENTS = 'payments';

    /**
     * Order processing queue.
     * Handles order confirmation, status updates, etc.
     */
    public const ORDERS = 'orders';

    /**
     * Email/notification queue.
     * Handles all outgoing emails and notifications.
     */
    public const NOTIFICATIONS = 'notifications';

    /**
     * Email queue (alias for NOTIFICATIONS).
     */
    public const EMAILS = 'notifications';

    /**
     * Shipping queue.
     * Handles shipping label creation, tracking updates.
     */
    public const SHIPPING = 'shipping';

    /**
     * Stock update queue.
     * Handles inventory adjustments.
     */
    public const STOCK = 'stock';

    /**
     * Report generation queue.
     * Handles heavy report generation tasks.
     */
    public const REPORTS = 'reports';

    /**
     * Webhook processing queue.
     * Handles incoming webhooks from external services.
     */
    public const WEBHOOKS = 'webhooks';

    /**
     * Get all queue names for worker configuration.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::HIGH,
            self::PAYMENTS,
            self::ORDERS,
            self::DEFAULT,
            self::NOTIFICATIONS,
            self::SHIPPING,
            self::STOCK,
            self::WEBHOOKS,
            self::REPORTS,
            self::LOW,
        ];
    }

    /**
     * Get queue names as comma-separated string for artisan command.
     * Ordered by priority.
     */
    public static function forWorker(): string
    {
        return implode(',', self::all());
    }
}

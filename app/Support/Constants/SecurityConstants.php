<?php

declare(strict_types=1);

namespace App\Support\Constants;

/**
 * @ai-context Security-related constants for the application.
 *             Centralizes security configuration values.
 * @ai-security These values should be reviewed and adjusted based on security requirements
 */
final class SecurityConstants
{
    /**
     * Password minimum length requirement.
     */
    public const PASSWORD_MIN_LENGTH = 8;

    /**
     * Password maximum length (to prevent DoS via bcrypt).
     */
    public const PASSWORD_MAX_LENGTH = 72;

    /**
     * Bcrypt hashing rounds.
     */
    public const BCRYPT_ROUNDS = 12;

    /**
     * Access token TTL in minutes.
     */
    public const ACCESS_TOKEN_TTL = 15; // 15 minutes

    /**
     * Refresh token TTL in days.
     */
    public const REFRESH_TOKEN_TTL_DAYS = 7;

    /**
     * Maximum failed login attempts before lockout.
     */
    public const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Account lockout duration in minutes.
     */
    public const LOCKOUT_DURATION = 15;

    /**
     * Rate limit for API requests per minute.
     */
    public const API_RATE_LIMIT = 60;

    /**
     * Rate limit for authentication endpoints per minute.
     */
    public const AUTH_RATE_LIMIT = 5;

    /**
     * Rate limit for refresh token endpoint per minute.
     */
    public const REFRESH_TOKEN_RATE_LIMIT = 5;

    /**
     * Password reset token expiration in minutes.
     */
    public const PASSWORD_RESET_TTL = 60;

    /**
     * Email verification token expiration in minutes.
     */
    public const EMAIL_VERIFICATION_TTL = 1440; // 24 hours

    /**
     * Maximum file upload size in kilobytes.
     */
    public const MAX_UPLOAD_SIZE = 5120; // 5MB

    /**
     * Allowed image mime types.
     */
    public const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Allowed image extensions.
     */
    public const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Session lifetime in minutes.
     */
    public const SESSION_LIFETIME = 120;

    /**
     * Webhook signature header name.
     */
    public const WEBHOOK_SIGNATURE_HEADER = 'X-Webhook-Signature';

    /**
     * CORS max age in seconds.
     */
    public const CORS_MAX_AGE = 86400;
}

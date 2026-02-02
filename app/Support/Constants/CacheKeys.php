<?php

declare(strict_types=1);

namespace App\Support\Constants;

/**
 * @ai-context Centralized cache keys and TTL constants.
 *             All cache keys MUST be defined here to prevent key collisions.
 * @ai-dependencies Used by cached repositories and services
 * @ai-flow Services/repositories use these constants when interacting with cache
 */
final class CacheKeys
{
    // Cache tags
    public const TAG_PRODUCTS = 'products';
    public const TAG_CATEGORIES = 'categories';
    public const TAG_USERS = 'users';
    public const TAG_ORDERS = 'orders';
    public const TAG_SETTINGS = 'settings';
    public const TAG_SHIPPING = 'shipping';

    // TTL values in seconds
    public const TTL_SHORT = 300;       // 5 minutes
    public const TTL_MEDIUM = 1800;     // 30 minutes
    public const TTL_LONG = 3600;       // 1 hour
    public const TTL_VERY_LONG = 86400; // 24 hours

    // Specific TTL values
    public const TTL_PRODUCTS = 3600;       // 1 hour
    public const TTL_CATEGORIES = 3600;     // 1 hour
    public const TTL_SETTINGS = 86400;      // 24 hours
    public const TTL_USER_PROFILE = 1800;   // 30 minutes
    public const TTL_SHIPPING_QUOTE = 300;  // 5 minutes

    // Key prefixes
    public const PREFIX_PRODUCT = 'products:';
    public const PREFIX_CATEGORY = 'categories:';
    public const PREFIX_USER = 'users:';
    public const PREFIX_ORDER = 'orders:';
    public const PREFIX_CART = 'carts:';
    public const PREFIX_SETTINGS = 'settings:';
    public const PREFIX_SHIPPING = 'shipping:';

    // Full key constants
    public const PRODUCTS_ALL = 'products:all';
    public const PRODUCTS_FEATURED = 'products:featured';
    public const CATEGORIES_ALL = 'categories:all';
    public const CATEGORIES_TREE = 'categories:tree';
    public const SETTINGS_PUBLIC = 'settings:public';
    public const SETTINGS_ALL = 'settings:all';

    /**
     * Get cache key for a single product.
     */
    public static function product(int|string $id): string
    {
        return self::PREFIX_PRODUCT . $id;
    }

    /**
     * Get cache key for a product by slug.
     */
    public static function productBySlug(string $slug): string
    {
        return self::PREFIX_PRODUCT . 'slug:' . $slug;
    }

    /**
     * Get cache key for products by category.
     */
    public static function productsByCategory(int $categoryId): string
    {
        return self::PREFIX_PRODUCT . 'category:' . $categoryId;
    }

    /**
     * Get cache key for a single category.
     */
    public static function category(int|string $id): string
    {
        return self::PREFIX_CATEGORY . $id;
    }

    /**
     * Get cache key for a category by slug.
     */
    public static function categoryBySlug(string $slug): string
    {
        return self::PREFIX_CATEGORY . 'slug:' . $slug;
    }

    /**
     * Get cache key for a single user.
     */
    public static function user(int|string $id): string
    {
        return self::PREFIX_USER . $id;
    }

    /**
     * Get cache key for user orders.
     */
    public static function userOrders(int $userId): string
    {
        return self::PREFIX_USER . $userId . ':orders';
    }

    /**
     * Get cache key for user wishlist.
     */
    public static function userWishlist(int $userId): string
    {
        return self::PREFIX_USER . $userId . ':wishlist';
    }

    /**
     * Get cache key for a single order.
     */
    public static function order(int|string $id): string
    {
        return self::PREFIX_ORDER . $id;
    }

    /**
     * Get cache key for a cart by user ID.
     */
    public static function cartByUser(int $userId): string
    {
        return self::PREFIX_CART . 'user:' . $userId;
    }

    /**
     * Get cache key for a cart by session ID.
     */
    public static function cartBySession(string $sessionId): string
    {
        return self::PREFIX_CART . 'session:' . $sessionId;
    }

    /**
     * Get cache key for a setting by key.
     */
    public static function setting(string $key): string
    {
        return self::PREFIX_SETTINGS . $key;
    }

    /**
     * Get cache key for settings by group.
     */
    public static function settingsByGroup(string $group): string
    {
        return self::PREFIX_SETTINGS . 'group:' . $group;
    }

    /**
     * Get cache key for shipping quote.
     */
    public static function shippingQuote(string $postalCode, float $weight): string
    {
        return self::PREFIX_SHIPPING . 'quote:' . $postalCode . ':' . $weight;
    }

    /**
     * Get cache key for shipping zones.
     */
    public static function shippingZones(): string
    {
        return self::PREFIX_SHIPPING . 'zones';
    }
}

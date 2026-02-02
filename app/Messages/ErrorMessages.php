<?php

declare(strict_types=1);

namespace App\Messages;

/**
 * @ai-context Centralized error messages for the entire application.
 *             All user-facing error strings MUST be defined here.
 *             Supports both static messages and parameterized messages.
 * @ai-dependencies None - standalone constants class
 * @ai-flow Used by exceptions and controllers to get consistent error messages
 */
final class ErrorMessages
{
    public const AUTH = [
        'INVALID_CREDENTIALS' => 'Invalid email or password',
        'TOKEN_EXPIRED' => 'Your session has expired. Please log in again',
        'TOKEN_INVALID' => 'Invalid or expired token',
        'UNAUTHORIZED' => 'You do not have permission to perform this action',
        'ACCOUNT_LOCKED' => 'Account locked due to too many failed attempts',
        'ACCOUNT_DISABLED' => 'Your account has been disabled',
        'EMAIL_NOT_VERIFIED' => 'Please verify your email address before continuing',
        'REFRESH_TOKEN_INVALID' => 'Invalid refresh token',
        'REFRESH_TOKEN_EXPIRED' => 'Refresh token has expired. Please log in again',
    ];

    public const USER = [
        'NOT_FOUND' => 'User not found',
        'EMAIL_EXISTS' => 'An account with this email already exists',
        'INVALID_EMAIL' => 'Please provide a valid email address',
        'INVALID_PASSWORD' => 'Password does not meet requirements',
        'CURRENT_PASSWORD_INCORRECT' => 'Current password is incorrect',
    ];

    public const CATEGORY = [
        'NOT_FOUND' => 'Category not found',
        'HAS_PRODUCTS' => 'Cannot delete category with associated products',
        'HAS_CHILDREN' => 'Cannot delete category with subcategories',
        'SLUG_EXISTS' => 'A category with this slug already exists',
        'CIRCULAR_REFERENCE' => 'Cannot set a subcategory as parent',
    ];

    public const PRODUCT = [
        'NOT_FOUND' => 'Product not found',
        'OUT_OF_STOCK' => 'This product is currently out of stock',
        'INSUFFICIENT_STOCK' => 'Not enough stock available',
        'SKU_EXISTS' => 'A product with this SKU already exists',
        'VARIANT_NOT_FOUND' => 'Product variant not found',
        'INVALID_PRICE' => 'Invalid price format',
    ];

    public const CART = [
        'NOT_FOUND' => 'Cart not found',
        'ITEM_NOT_FOUND' => 'Cart item not found',
        'EMPTY' => 'Your cart is empty',
        'PRODUCT_UNAVAILABLE' => 'One or more products in your cart are no longer available',
        'QUANTITY_EXCEEDED' => 'Requested quantity exceeds available stock',
    ];

    public const ORDER = [
        'NOT_FOUND' => 'Order not found',
        'CANNOT_CANCEL' => 'This order cannot be cancelled',
        'ALREADY_PAID' => 'This order has already been paid',
        'ALREADY_SHIPPED' => 'This order has already been shipped',
        'INVALID_STATUS_TRANSITION' => 'Invalid order status transition',
        'EMPTY_CART' => 'Cannot create order from empty cart',
    ];

    public const PAYMENT = [
        'FAILED' => 'Payment processing failed. Please try again',
        'DECLINED' => 'Your payment was declined',
        'REFUND_FAILED' => 'Refund could not be processed',
        'INVALID_AMOUNT' => 'Invalid payment amount',
        'ALREADY_PROCESSED' => 'This payment has already been processed',
        'GATEWAY_ERROR' => 'Payment gateway error. Please try again later',
    ];

    public const SHIPPING = [
        'QUOTE_FAILED' => 'Unable to calculate shipping cost',
        'CREATION_FAILED' => 'Unable to create shipment',
        'TRACKING_FAILED' => 'Unable to retrieve tracking information',
        'TRACKING_NOT_AVAILABLE' => 'Tracking information not available',
        'INVALID_ADDRESS' => 'Invalid shipping address',
        'ZONE_NOT_COVERED' => 'Shipping not available to this location',
    ];

    public const CUSTOMER = [
        'ADDRESS_NOT_FOUND' => 'Address not found',
        'ADDRESS_IN_USE' => 'Cannot delete address used in pending orders',
        'MAX_ADDRESSES_REACHED' => 'Maximum number of saved addresses reached',
    ];

    public const WISHLIST = [
        'ALREADY_EXISTS' => 'Product already in wishlist',
        'NOT_FOUND' => 'Wishlist item not found',
    ];

    public const SETTINGS = [
        'NOT_FOUND' => 'Setting not found',
        'INVALID_VALUE' => 'Invalid setting value',
        'READ_ONLY' => 'This setting cannot be modified',
    ];

    public const GENERAL = [
        'SERVER_ERROR' => 'An unexpected error occurred. Please try again later',
        'VALIDATION_FAILED' => 'Validation failed',
        'NOT_FOUND' => 'Resource not found',
        'RATE_LIMITED' => 'Too many requests. Please try again later',
        'MAINTENANCE' => 'System is under maintenance. Please try again later',
        'FORBIDDEN' => 'Access denied',
    ];

    /**
     * Get entity not found message.
     */
    public static function entityNotFound(string $entity): string
    {
        return "{$entity} not found";
    }

    /**
     * Get insufficient stock message with details.
     */
    public static function insufficientStock(string $product, int $available): string
    {
        return "Only {$available} units of {$product} are available";
    }

    /**
     * Get field required message.
     */
    public static function fieldRequired(string $field): string
    {
        return "The {$field} field is required";
    }

    /**
     * Get field min length message.
     */
    public static function fieldMinLength(string $field, int $min): string
    {
        return "The {$field} must be at least {$min} characters";
    }

    /**
     * Get field max length message.
     */
    public static function fieldMaxLength(string $field, int $max): string
    {
        return "The {$field} must not exceed {$max} characters";
    }

    /**
     * Get unique constraint violation message.
     */
    public static function alreadyExists(string $entity, string $field): string
    {
        return "A {$entity} with this {$field} already exists";
    }

    /**
     * Get invalid format message.
     */
    public static function invalidFormat(string $field): string
    {
        return "The {$field} format is invalid";
    }
}

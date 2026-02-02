<?php

declare(strict_types=1);

namespace App\Messages;

/**
 * @ai-context Centralized success messages for the entire application.
 *             All user-facing success strings MUST be defined here.
 * @ai-dependencies None - standalone constants class
 * @ai-flow Used by controllers to return consistent success messages
 */
final class SuccessMessages
{
    public const AUTH = [
        'LOGIN' => 'Welcome back!',
        'LOGOUT' => 'You have been logged out successfully',
        'REGISTERED' => 'Account created successfully',
        'PASSWORD_RESET' => 'Password has been reset successfully',
        'PASSWORD_RESET_SENT' => 'Password reset email sent',
        'EMAIL_VERIFIED' => 'Email verified successfully',
        'TOKEN_REFRESHED' => 'Token refreshed successfully',
    ];

    public const USER = [
        'CREATED' => 'User created successfully',
        'UPDATED' => 'Profile updated successfully',
        'DELETED' => 'Account deleted successfully',
        'PASSWORD_CHANGED' => 'Password changed successfully',
    ];

    public const CATEGORY = [
        'CREATED' => 'Category created successfully',
        'UPDATED' => 'Category updated successfully',
        'DELETED' => 'Category deleted successfully',
    ];

    public const PRODUCT = [
        'CREATED' => 'Product created successfully',
        'UPDATED' => 'Product updated successfully',
        'DELETED' => 'Product deleted successfully',
        'STOCK_UPDATED' => 'Stock updated successfully',
        'IMAGE_UPLOADED' => 'Image uploaded successfully',
        'IMAGE_DELETED' => 'Image deleted successfully',
    ];

    public const CART = [
        'ITEM_ADDED' => 'Item added to cart',
        'ITEM_UPDATED' => 'Cart updated successfully',
        'ITEM_REMOVED' => 'Item removed from cart',
        'CLEARED' => 'Cart cleared successfully',
    ];

    public const ORDER = [
        'CREATED' => 'Order placed successfully',
        'CANCELLED' => 'Order cancelled successfully',
        'UPDATED' => 'Order updated successfully',
        'STATUS_UPDATED' => 'Order status updated successfully',
    ];

    public const PAYMENT = [
        'INITIATED' => 'Payment initiated successfully',
        'COMPLETED' => 'Payment completed successfully',
        'REFUNDED' => 'Refund processed successfully',
    ];

    public const SHIPPING = [
        'QUOTE_GENERATED' => 'Shipping quote generated',
        'SHIPMENT_CREATED' => 'Shipment created successfully',
        'TRACKING_UPDATED' => 'Tracking information updated',
    ];

    public const CUSTOMER = [
        'ADDRESS_CREATED' => 'Address saved successfully',
        'ADDRESS_UPDATED' => 'Address updated successfully',
        'ADDRESS_DELETED' => 'Address deleted successfully',
        'ADDRESS_SET_DEFAULT' => 'Default address updated',
    ];

    public const WISHLIST = [
        'ADDED' => 'Product added to wishlist',
        'REMOVED' => 'Product removed from wishlist',
    ];

    public const SETTINGS = [
        'UPDATED' => 'Settings updated successfully',
    ];

    public const GENERAL = [
        'OPERATION_SUCCESSFUL' => 'Operation completed successfully',
        'DATA_RETRIEVED' => 'Data retrieved successfully',
    ];
}

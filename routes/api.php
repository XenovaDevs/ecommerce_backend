<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ShippingController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminContactController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminShipmentController;
use App\Http\Controllers\Api\V1\Admin\AdminShippingController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\UploadController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Middleware\WebhookRateLimit;
use App\Support\Constants\SecurityConstants;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API V1 Routes
| All routes are prefixed with /api/v1
|
*/

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Public Routes (No Authentication Required)
    |--------------------------------------------------------------------------
    */

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:' . SecurityConstants::AUTH_RATE_LIMIT . ',1');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:' . SecurityConstants::AUTH_RATE_LIMIT . ',1');
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:' . SecurityConstants::REFRESH_TOKEN_RATE_LIMIT . ',1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:1,1'); // 1 request per 60 seconds
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:' . SecurityConstants::AUTH_RATE_LIMIT . ',1');
    });

    // Public Settings
    Route::get('/settings/public', [SettingController::class, 'public']);

    // Categories (public read)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // Products (public read)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/{id}/related', [ProductController::class, 'related'])->where('id', '[0-9]+');
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Reviews (public read)
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);

    // Cart (guest access with session)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('/items/{id}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/merge', [CartController::class, 'merge'])->middleware('auth:sanctum');

        // Coupon management (guest and authenticated)
        Route::post('/coupons', [CouponController::class, 'apply']);
        Route::delete('/coupons/{coupon}', [CouponController::class, 'remove']);
    });

    // Shipping (public)
    Route::post('/shipping/quote', [ShippingController::class, 'quote']);
    Route::get('/shipping/track/{trackingNumber}', [ShippingController::class, 'track']);

    // Contact Form (public)
    Route::post('/contact', [ContactController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Webhooks (External Services)
    |--------------------------------------------------------------------------
    */
    Route::prefix('webhooks')->middleware(WebhookRateLimit::class . ':100,1')->group(function () {
        Route::post('/mercadopago', [WebhookController::class, 'mercadoPago']);
        Route::post('/andreani', [WebhookController::class, 'andreani']);
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes (Customer)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum'])->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Customer Profile
        Route::prefix('customer')->group(function () {
            Route::get('/profile', [CustomerController::class, 'show']);
            Route::put('/profile', [CustomerController::class, 'update']);

            // Addresses
            Route::get('/addresses', [CustomerAddressController::class, 'index']);
            Route::post('/addresses', [CustomerAddressController::class, 'store']);
            Route::put('/addresses/{id}', [CustomerAddressController::class, 'update']);
            Route::delete('/addresses/{id}', [CustomerAddressController::class, 'destroy']);
            Route::put('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);

            // Orders
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{id}', [OrderController::class, 'show']);
            Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        });

        // Aliases for frontend compatibility: /user/* → /customer/*
        Route::prefix('user')->group(function () {
            Route::get('/profile', [CustomerController::class, 'show']);
            Route::put('/profile', [CustomerController::class, 'update']);
            Route::get('/addresses', [CustomerAddressController::class, 'index']);
            Route::post('/addresses', [CustomerAddressController::class, 'store']);
            Route::put('/addresses/{id}', [CustomerAddressController::class, 'update']);
            Route::delete('/addresses/{id}', [CustomerAddressController::class, 'destroy']);
        });

        // Aliases for frontend compatibility: /orders/* → /customer/orders/*
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        });

        // Wishlist
        Route::prefix('wishlist')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        });

        // Reviews (authenticated actions)
        Route::prefix('reviews')->group(function () {
            Route::post('/', [ReviewController::class, 'store']);
            Route::put('/{review}', [ReviewController::class, 'update']);
            Route::delete('/{review}', [ReviewController::class, 'destroy']);
            Route::post('/{review}/helpful', [ReviewController::class, 'markHelpful']);
        });

        // Checkout
        Route::post('/checkout', [CheckoutController::class, 'process']);
        Route::prefix('checkout')->group(function () {
            Route::post('/validate', [CheckoutController::class, 'validate']);
            Route::post('/payment-preference', [CheckoutController::class, 'createPaymentPreference']);
        });
        Route::get('/payments/methods', [CheckoutController::class, 'getPaymentMethods']);

        // Payments
        Route::prefix('payments')->group(function () {
            Route::post('/create', [PaymentController::class, 'create']);
            Route::post('/preference', [PaymentController::class, 'create']); // Alias
            Route::get('/{id}/status', [PaymentController::class, 'status']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('ability:dashboard.view');

        // Categories Management
        Route::get('/categories', [AdminCategoryController::class, 'index'])
            ->middleware('ability:categories.view');
        Route::post('/categories', [AdminCategoryController::class, 'store'])
            ->middleware('ability:categories.create');
        Route::get('/categories/{id}', [AdminCategoryController::class, 'show'])
            ->middleware('ability:categories.view');
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update'])
            ->middleware('ability:categories.update');
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy'])
            ->middleware('ability:categories.delete');

        // Products Management
        Route::get('/products', [AdminProductController::class, 'index'])
            ->middleware('ability:products.view');
        Route::post('/products', [AdminProductController::class, 'store'])
            ->middleware('ability:products.create');
        Route::get('/products/{id}', [AdminProductController::class, 'show'])
            ->middleware('ability:products.view');
        Route::put('/products/{id}', [AdminProductController::class, 'update'])
            ->middleware('ability:products.update');
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy'])
            ->middleware('ability:products.delete');
        Route::post('/products/{id}/images', [AdminProductController::class, 'uploadImage'])
            ->middleware('ability:products.manage-images');
        Route::delete('/products/{id}/images/{imageId}', [AdminProductController::class, 'deleteImage'])
            ->middleware('ability:products.manage-images');
        Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDelete'])
            ->middleware('ability:products.delete');

        // Orders Management
        Route::get('/orders', [AdminOrderController::class, 'index'])
            ->middleware('ability:orders.view-all');
        Route::get('/orders/{id}', [AdminOrderController::class, 'show'])
            ->middleware('ability:orders.view-all');
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])
            ->middleware('ability:orders.update-status');
        Route::get('/orders/stats', [AdminOrderController::class, 'stats'])
            ->middleware('ability:orders.view-all');

        // Shipment Management
        Route::post('/shipments/orders/{order}', [AdminShipmentController::class, 'create'])
            ->middleware('ability:orders.create-shipment');

        // Customers Management
        Route::get('/customers', [AdminCustomerController::class, 'index'])
            ->middleware('ability:customers.view');
        Route::get('/customers/{id}', [AdminCustomerController::class, 'show'])
            ->middleware('ability:customers.view');
        Route::get('/customers/{id}/orders', [AdminCustomerController::class, 'orders'])
            ->middleware('ability:customers.view');

        // Settings Management
        Route::get('/settings', [AdminSettingController::class, 'index'])
            ->middleware('ability:settings.view');
        Route::put('/settings', [AdminSettingController::class, 'update'])
            ->middleware('ability:settings.update');
        Route::get('/settings/general', [AdminSettingController::class, 'getByGroup'])
            ->defaults('group', 'general')
            ->middleware('ability:settings.view');
        Route::get('/settings/shipping', [AdminSettingController::class, 'getByGroup'])
            ->defaults('group', 'shipping')
            ->middleware('ability:settings.view');
        Route::get('/settings/payment', [AdminSettingController::class, 'getByGroup'])
            ->defaults('group', 'payment')
            ->middleware('ability:settings.view');
        Route::get('/settings/{key}', [AdminSettingController::class, 'show'])
            ->middleware('ability:settings.view');

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales'])
                ->middleware('ability:reports.view-sales');
            Route::get('/products', [ReportController::class, 'products'])
                ->middleware('ability:reports.view-products');
            Route::get('/customers', [ReportController::class, 'customers'])
                ->middleware('ability:reports.view-customers');
            Route::get('/export', [ReportController::class, 'export'])
                ->middleware('ability:reports.view-sales');
        });

        // Shipping Management
        Route::get('/shipping/rates', [AdminShippingController::class, 'rates'])
            ->middleware('ability:settings.view');

        // File Upload
        Route::post('/upload/image', [UploadController::class, 'uploadImage'])
            ->middleware('ability:products.create');
        Route::post('/upload/images', [UploadController::class, 'uploadImages'])
            ->middleware('ability:products.create');

        // Contact Messages Management
        Route::get('/contacts', [AdminContactController::class, 'index'])
            ->middleware('ability:contacts.view');
        Route::get('/contacts/{id}', [AdminContactController::class, 'show'])
            ->middleware('ability:contacts.view');
        Route::put('/contacts/{id}/reply', [AdminContactController::class, 'reply'])
            ->middleware('ability:contacts.reply');
        Route::put('/contacts/{id}/status', [AdminContactController::class, 'updateStatus'])
            ->middleware('ability:contacts.update-status');

        // Reviews Management
        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::put('/reviews/{review}/approve', [AdminReviewController::class, 'approve']);
        Route::put('/reviews/{review}/reject', [AdminReviewController::class, 'reject']);
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy']);
    });
});

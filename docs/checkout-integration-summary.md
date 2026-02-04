# Checkout Flow Integration - Implementation Summary

## Overview
Complete integration of the checkout flow including coupons, correct total calculations, and Mercado Pago payment URL generation.

## Modified Files

### 1. `app/DTOs/Order/CreateOrderDTO.php`
**Changes:**
- Renamed properties to camelCase for consistency (shippingAddress, billingAddress, shippingCost, paymentMethod)
- Added `shippingCost` property (float, default 0.0)
- Added static `fromRequest()` method to construct DTO from HTTP request
- Updated `toArray()` method to reflect new property names

**Key Methods:**
```php
public static function fromRequest(Request $request): self
```

### 2. `app/Http/Requests/Order/CheckoutRequest.php`
**Changes:**
- Added `shipping_cost` validation: `['required', 'numeric', 'min:0']`
- Added `address_line_2` as nullable for both shipping and billing addresses
- Ensures complete address validation

**New Validation Rules:**
- `shipping_cost`: required, numeric, min:0
- `shipping_address.address_line_2`: nullable, string, max:500
- `billing_address.address_line_2`: nullable, string, max:500

### 3. `app/Services/Order/OrderCalculationService.php`
**Changes:**
- Added dependency injection for `CouponService`
- Integrated coupon discount calculation using `CouponService::calculateCartDiscount()`
- Modified tax calculation to apply on (subtotal - discount) instead of just subtotal
- Enhanced calculation flow: subtotal → discount → shipping → tax → total
- Added comprehensive documentation

**Calculation Flow:**
1. Calculate subtotal from cart items
2. Calculate coupon discounts (via CouponService)
3. Calculate shipping (with free shipping threshold check)
4. Calculate tax on (subtotal - discount)
5. Calculate final total: subtotal - discount + tax + shipping

**Key Method Signature:**
```php
public function calculate(Cart $cart, float $shippingCost = 0): array
// Returns: ['subtotal', 'discount', 'tax', 'shipping', 'total']
```

### 4. `app/Services/Order/OrderService.php`
**Changes:**
- Added dependency injection for `CouponService` and `PaymentService`
- Enhanced `createFromCart()` to return array with both order and payment_url
- Added coupon validation before order creation
- Added coupon usage recording after order creation
- Integrated Mercado Pago payment preference creation
- Added comprehensive error handling and logging
- Added private helper methods for coupon operations

**New Helper Methods:**
- `validateCartCoupons(Cart $cart)`: Validates all coupons are still valid
- `recordCouponUsage(Cart $cart, User $user, Order $order)`: Records usage for all applied coupons

**Return Value Changed:**
```php
// Before:
public function createFromCart(User $user, CreateOrderDTO $dto): Order

// After:
public function createFromCart(User $user, CreateOrderDTO $dto): array
// Returns: ['order' => Order, 'payment_url' => string|null]
```

**Complete Flow:**
1. Validate cart exists and not empty
2. Validate cart items (stock, prices)
3. Validate all applied coupons
4. Create shipping and billing addresses
5. Calculate totals (with coupon discounts)
6. Create order record
7. Create order items and decrease stock
8. Record coupon usage (increment used_count, create CouponUsage records)
9. Add order status history
10. Create Mercado Pago payment preference
11. Clear cart
12. Return order + payment_url

### 5. `app/Http/Controllers/Api/V1/OrderController.php`
**Changes:**
- Changed request type from `CreateOrderRequest` to `CheckoutRequest`
- Updated `checkout()` method to handle new array response from OrderService
- Modified response structure to include both order and payment_url

**New Response Structure:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": { ... OrderResource ... },
    "payment_url": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=xxx"
  }
}
```

## Architecture Principles Applied

### Single Responsibility Principle (SRP)
- **OrderCalculationService**: Only responsible for calculations, delegates coupon discount logic to CouponService
- **CouponService**: Only handles coupon validation, application, and usage recording
- **PaymentService**: Only handles payment orchestration and Mercado Pago integration
- **OrderService**: Orchestrates the complete checkout flow, delegating specific tasks to specialized services

### Dependency Inversion Principle (DIP)
- All services depend on abstractions (interfaces implied through Laravel's container)
- Dependencies injected via constructor, not hard-coded instantiation
- Easy to mock services for testing

### Open/Closed Principle (OCP)
- OrderCalculationService can be extended with new calculation rules without modifying existing code
- Payment gateways can be added by implementing the same interface pattern
- Coupon types can be extended through the Coupon model's polymorphic behavior

## API Contract

### Checkout Request
```http
POST /api/v1/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "shipping_address": {
    "name": "John Doe",
    "phone": "+54911234567",
    "address": "Av. Corrientes 1234",
    "address_line_2": "Apt 5B",
    "city": "Buenos Aires",
    "state": "CABA",
    "postal_code": "C1043",
    "country": "Argentina"
  },
  "billing_address": { /* same structure */ },
  "shipping_cost": 500.00,
  "notes": "Please ring doorbell",
  "payment_method": "mercadopago"
}
```

### Checkout Response
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": {
      "id": 123,
      "status": "pending",
      "payment_status": "pending",
      "subtotal": 2500.00,
      "discount": 250.00,
      "tax": 472.50,
      "shipping_cost": 500.00,
      "total": 3222.50,
      "items": [...],
      "shipping_address": {...},
      "billing_address": {...}
    },
    "payment_url": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=xxx"
  }
}
```

## Coupon Integration Details

### Coupon Validation
- Validated before order creation via `validateCartCoupons()`
- Each coupon checked for:
  - Active status
  - Valid date range (starts_at, expires_at)
  - Usage limits (max_uses vs used_count)
  - Minimum amount requirements

### Coupon Discount Calculation
- Performed by `CouponService::calculateCartDiscount()`
- Applies coupons sequentially (each reduces the amount for the next)
- Only applies if coupon is valid for remaining amount
- Handles both percentage and fixed amount discounts

### Coupon Usage Recording
- Creates `CouponUsage` record linking coupon, user, and order
- Increments `Coupon.used_count`
- Records actual discount amount applied
- All within database transaction for consistency

## Payment Integration Details

### Mercado Pago Flow
1. Order created with status PENDING
2. Payment record created via `PaymentService::createPaymentPreference()`
3. Mercado Pago preference created with order items
4. Payment record updated with external_id (preference_id)
5. Payment URL (init_point) returned to frontend
6. User redirected to Mercado Pago for payment
7. Webhook updates payment and order status when paid

### Payment Preference Data
- Items: All order items with quantities and prices
- Payer: User name and email
- Back URLs: Success, failure, pending (configured in services.mercadopago)
- External Reference: Payment ID (for webhook matching)
- Notification URL: Webhook endpoint for payment updates

### Error Handling
- If payment preference creation fails, order is still created
- Error logged but not thrown (prevents blocking order creation)
- User can retry payment later via PaymentController
- Payment status remains PENDING until webhook confirms payment

## Testing Considerations

### Mocking Requirements
For tests, mock the following services:
- `PaymentService::createPaymentPreference()` - Returns mock payment URL
- `MercadoPagoService` - Prevents actual API calls

### Test Data Requirements
- Products with sufficient stock
- Valid shipping/billing addresses
- Active coupons (if testing with coupons)
- Mercado Pago configuration (or mocked)

### Key Test Scenarios
1. Checkout with empty cart → 422 error
2. Checkout with insufficient stock → 422 error
3. Checkout with invalid coupon → 422 error
4. Successful checkout without coupon → 201 with payment_url
5. Successful checkout with coupon → 201 with correct discount applied
6. Payment method = 'cash' → No payment_url returned

## Database Changes Required

None - all existing tables support this implementation:
- `orders`: Has discount, tax, shipping_cost, total columns
- `coupon_usages`: Records coupon usage per order
- `coupons`: Tracks used_count
- `payments`: Links to orders and tracks external_id
- `cart_coupons`: Pivot table for cart-coupon relationship

## Configuration Required

### Environment Variables (.env)
```env
MERCADOPAGO_ACCESS_TOKEN=your_access_token
MERCADOPAGO_WEBHOOK_SECRET=your_webhook_secret
MERCADOPAGO_SUCCESS_URL=https://yourstore.com/payment/success
MERCADOPAGO_FAILURE_URL=https://yourstore.com/payment/failure
MERCADOPAGO_PENDING_URL=https://yourstore.com/payment/pending
MERCADOPAGO_NOTIFICATION_URL=https://yourstore.com/api/v1/webhooks/mercadopago
```

### Settings Table
- `tax_enabled`: Enable/disable tax calculation
- `tax_included_in_prices`: Whether prices include tax
- `tax_rate`: Tax percentage (e.g., 21 for 21%)
- `free_shipping_threshold`: Minimum amount for free shipping
- `currency`: Currency code (e.g., 'ARS')

## Deployment Checklist

- [ ] Run database migrations (if any new)
- [ ] Configure Mercado Pago credentials
- [ ] Set up webhook URL in Mercado Pago dashboard
- [ ] Configure back URLs for payment flow
- [ ] Update frontend to send shipping_cost in checkout request
- [ ] Update frontend to handle payment_url in response
- [ ] Test checkout flow in sandbox environment
- [ ] Monitor logs for payment errors
- [ ] Set up alerts for failed payment preference creation

## Known Limitations

1. **Payment Gateway**: Currently only supports Mercado Pago
   - Solution: Implement additional payment gateways following the same pattern

2. **Coupon Stacking**: All coupons applied sequentially
   - Consideration: May want to limit to one coupon per order

3. **Tax Calculation**: Simple percentage-based calculation
   - Consideration: May need complex tax rules for multiple jurisdictions

4. **Shipping Cost**: Must be provided by frontend
   - Future: Integrate real-time shipping API (Andreani) for accurate quotes

## Future Enhancements

1. **Retry Payment**: Allow users to retry payment for failed orders
2. **Payment Methods**: Add support for other gateways (Stripe, PayPal)
3. **Shipping Integration**: Auto-calculate shipping via Andreani API
4. **Inventory Reservation**: Reserve stock for X minutes during checkout
5. **Order Expiration**: Automatically cancel unpaid orders after timeout
6. **Guest Checkout**: Allow checkout without authentication
7. **Saved Addresses**: Quick checkout with saved addresses
8. **Gift Cards**: Support for gift card payment method

## Related Documentation

- [Coupon System Documentation](./coupon-system.md)
- [Payment Integration Documentation](./payment-integration.md)
- [Order Management Documentation](./order-management.md)
- [API Endpoints Reference](./api-endpoints.md)

---
**Implementation Date**: 2026-02-03
**Developer**: Claude Sonnet 4.5
**Status**: Complete - Ready for Testing

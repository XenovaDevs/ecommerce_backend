# Checkout API - Quick Reference Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Basic Checkout](#basic-checkout)
3. [Checkout with Coupons](#checkout-with-coupons)
4. [Error Handling](#error-handling)
5. [Testing](#testing)

## Prerequisites

Before initiating checkout, ensure:

1. User is authenticated
2. Cart has items
3. Shipping cost is calculated
4. Addresses are ready (shipping and billing)

## Basic Checkout

### Endpoint
```
POST /api/v1/checkout
```

### Headers
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "shipping_address": {
    "name": "Juan Pérez",
    "phone": "+54 9 11 1234-5678",
    "address": "Av. Corrientes 1234",
    "address_line_2": "Piso 5, Depto B",
    "city": "Buenos Aires",
    "state": "CABA",
    "postal_code": "C1043AAZ",
    "country": "Argentina"
  },
  "billing_address": {
    "name": "Juan Pérez",
    "phone": "+54 9 11 1234-5678",
    "address": "Av. Corrientes 1234",
    "address_line_2": "Piso 5, Depto B",
    "city": "Buenos Aires",
    "state": "CABA",
    "postal_code": "C1043AAZ",
    "country": "Argentina"
  },
  "shipping_cost": 500.00,
  "notes": "Please ring doorbell. Gate code: 1234",
  "payment_method": "mercadopago"
}
```

### Success Response (201 Created)
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-20260203-00123",
      "status": "pending",
      "payment_status": "pending",
      "subtotal": 2500.00,
      "discount": 0.00,
      "tax": 525.00,
      "shipping_cost": 500.00,
      "total": 3525.00,
      "notes": "Please ring doorbell. Gate code: 1234",
      "created_at": "2026-02-03T14:30:00.000000Z",
      "items": [
        {
          "id": 456,
          "product_id": 10,
          "name": "Product Name",
          "sku": "PRD-001",
          "quantity": 2,
          "price": 1000.00,
          "total": 2000.00,
          "options": {
            "size": "L",
            "color": "Blue"
          }
        },
        {
          "id": 457,
          "product_id": 11,
          "name": "Another Product",
          "sku": "PRD-002",
          "quantity": 1,
          "price": 500.00,
          "total": 500.00,
          "options": null
        }
      ],
      "shipping_address": {
        "id": 789,
        "name": "Juan Pérez",
        "phone": "+54 9 11 1234-5678",
        "address": "Av. Corrientes 1234",
        "address_line_2": "Piso 5, Depto B",
        "city": "Buenos Aires",
        "state": "CABA",
        "postal_code": "C1043AAZ",
        "country": "Argentina"
      },
      "billing_address": {
        "id": 790,
        "name": "Juan Pérez",
        "phone": "+54 9 11 1234-5678",
        "address": "Av. Corrientes 1234",
        "address_line_2": "Piso 5, Depto B",
        "city": "Buenos Aires",
        "state": "CABA",
        "postal_code": "C1043AAZ",
        "country": "Argentina"
      }
    },
    "payment_url": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=123456789-abcd-1234-efgh-123456789012"
  }
}
```

### Frontend Flow
```javascript
// 1. Call checkout API
const response = await fetch('/api/v1/checkout', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(checkoutData)
});

const result = await response.json();

if (result.success) {
  // 2. Redirect user to payment URL
  window.location.href = result.data.payment_url;

  // OR open in new window
  // window.open(result.data.payment_url, '_blank');
}
```

## Checkout with Coupons

### Step 1: Apply Coupon to Cart
```
POST /api/v1/cart/coupons
Content-Type: application/json

{
  "code": "SAVE10"
}
```

Response:
```json
{
  "success": true,
  "message": "Coupon applied successfully",
  "data": {
    "cart": {
      "subtotal": 2500.00,
      "discount": 250.00,
      "tax": 472.50,
      "total": 2722.50,
      "applied_coupons": [
        {
          "id": 5,
          "code": "SAVE10",
          "type": "percentage",
          "value": 10,
          "description": "10% off on all products"
        }
      ]
    }
  }
}
```

### Step 2: Proceed with Checkout
```
POST /api/v1/checkout
```

Use the same request body as basic checkout. The applied coupon will automatically be:
- Validated (active, not expired, usage limit)
- Applied to discount calculation
- Recorded in coupon_usages table
- Used count incremented

Success response will show discount:
```json
{
  "data": {
    "order": {
      "subtotal": 2500.00,
      "discount": 250.00,
      "tax": 472.50,
      "shipping_cost": 500.00,
      "total": 3222.50
    }
  }
}
```

## Error Handling

### Empty Cart (422)
```json
{
  "success": false,
  "error": {
    "message": "Cart is empty",
    "code": "EMPTY_CART"
  }
}
```

### Validation Error (422)
```json
{
  "success": false,
  "error": {
    "message": "The given data was invalid.",
    "code": "VALIDATION_ERROR",
    "details": {
      "shipping_address.name": [
        "The shipping address name field is required."
      ],
      "shipping_cost": [
        "The shipping cost field is required.",
        "The shipping cost must be at least 0."
      ]
    }
  }
}
```

### Stock Unavailable (422)
```json
{
  "success": false,
  "error": {
    "message": "Some items in your cart are no longer available",
    "code": "CART_VALIDATION_FAILED",
    "details": {
      "items": [
        {
          "product_id": 10,
          "name": "Product Name",
          "requested": 5,
          "available": 2,
          "error": "Insufficient stock"
        }
      ]
    }
  }
}
```

### Invalid Coupon (422)
```json
{
  "success": false,
  "error": {
    "message": "Coupon 'SAVE10' is no longer valid: This coupon has expired",
    "code": "INVALID_COUPON",
    "details": {
      "coupon_code": "SAVE10"
    }
  }
}
```

### Payment Creation Failed (Partial Success)
Order is created, but payment URL is null. User can retry payment later.

```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": { /* order details */ },
    "payment_url": null
  }
}
```

Frontend should handle this case:
```javascript
if (result.success && !result.data.payment_url) {
  // Show order confirmation
  // Offer manual payment option or retry button
  alert('Order created, but payment could not be initialized. Please try again.');

  // Can retry payment via:
  // POST /api/v1/payments/create
  // { order_id: result.data.order.id }
}
```

## Testing

### Test Data Preparation

#### 1. Create Test User
```bash
php artisan tinker
```
```php
$user = User::factory()->create([
    'email' => 'test@example.com',
    'password' => bcrypt('password')
]);
$token = $user->createToken('test')->plainTextToken;
echo $token;
```

#### 2. Create Test Products
```php
$product1 = Product::factory()->create([
    'name' => 'Test Product 1',
    'price' => 1000,
    'stock' => 50
]);

$product2 = Product::factory()->create([
    'name' => 'Test Product 2',
    'price' => 500,
    'stock' => 30
]);
```

#### 3. Add Items to Cart
```bash
curl -X POST http://localhost:8000/api/v1/cart \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 2
  }'
```

#### 4. Create Test Coupon
```php
$coupon = Coupon::create([
    'code' => 'TEST10',
    'type' => 'percentage',
    'value' => 10,
    'is_active' => true,
    'expires_at' => now()->addDays(30)
]);
```

#### 5. Apply Coupon
```bash
curl -X POST http://localhost:8000/api/v1/cart/coupons \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "TEST10"
  }'
```

#### 6. Perform Checkout
```bash
curl -X POST http://localhost:8000/api/v1/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "shipping_address": {
      "name": "Test User",
      "phone": "+54 9 11 1234-5678",
      "address": "Test Street 123",
      "city": "Buenos Aires",
      "state": "CABA",
      "postal_code": "C1000",
      "country": "Argentina"
    },
    "billing_address": {
      "name": "Test User",
      "phone": "+54 9 11 1234-5678",
      "address": "Test Street 123",
      "city": "Buenos Aires",
      "state": "CABA",
      "postal_code": "C1000",
      "country": "Argentina"
    },
    "shipping_cost": 500,
    "payment_method": "mercadopago"
  }'
```

### Automated Testing

#### Feature Test Example
```php
public function test_successful_checkout_with_coupon()
{
    // Arrange
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 1000,
        'stock' => 10
    ]);
    $coupon = Coupon::factory()->create([
        'code' => 'TEST10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true
    ]);

    // Add to cart
    $this->actingAs($user)
        ->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);

    // Apply coupon
    $this->postJson('/api/v1/cart/coupons', [
        'code' => 'TEST10'
    ]);

    // Act - Checkout
    $response = $this->postJson('/api/v1/checkout', [
        'shipping_address' => [
            'name' => 'Test User',
            'phone' => '+54 9 11 1234-5678',
            'address' => 'Test Street 123',
            'city' => 'Buenos Aires',
            'state' => 'CABA',
            'postal_code' => 'C1000',
            'country' => 'Argentina'
        ],
        'billing_address' => [
            'name' => 'Test User',
            'phone' => '+54 9 11 1234-5678',
            'address' => 'Test Street 123',
            'city' => 'Buenos Aires',
            'state' => 'CABA',
            'postal_code' => 'C1000',
            'country' => 'Argentina'
        ],
        'shipping_cost' => 500,
        'payment_method' => 'mercadopago'
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'order' => [
                    'id',
                    'subtotal',
                    'discount',
                    'tax',
                    'shipping_cost',
                    'total'
                ],
                'payment_url'
            ]
        ]);

    // Verify discount applied
    $this->assertEquals(200, $response->json('data.order.discount'));

    // Verify coupon usage recorded
    $this->assertDatabaseHas('coupon_usages', [
        'coupon_id' => $coupon->id,
        'user_id' => $user->id
    ]);

    // Verify stock decreased
    $this->assertEquals(8, $product->fresh()->stock);
}
```

### Mock Mercado Pago in Tests
```php
// In setUp() method
$this->mock(PaymentService::class, function ($mock) {
    $mock->shouldReceive('createPaymentPreference')
        ->andReturn([
            'payment_id' => 1,
            'preference_id' => 'test-preference-id',
            'init_point' => 'https://test.mercadopago.com/checkout',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout'
        ]);
});
```

## Common Issues and Solutions

### Issue 1: Missing Shipping Cost
**Error**: Validation error on shipping_cost field
**Solution**: Always calculate and include shipping_cost in request

### Issue 2: Payment URL is Null
**Cause**: Mercado Pago configuration missing or API error
**Solution**: Check .env for MERCADOPAGO_ACCESS_TOKEN, verify API connectivity

### Issue 3: Coupon Not Applied
**Cause**: Coupon expired, usage limit reached, or minimum amount not met
**Solution**: Check coupon validation response, verify coupon is still valid

### Issue 4: Stock Validation Failed After Adding to Cart
**Cause**: Stock changed between adding to cart and checkout
**Solution**: Show error, update cart, ask user to adjust quantities

### Issue 5: Tax Calculation Unexpected
**Cause**: Tax settings in database
**Solution**: Check settings table for tax_enabled, tax_rate, tax_included_in_prices

## Best Practices

1. **Always Validate Cart Before Showing Checkout**
   ```javascript
   await validateCart(); // Check stock, prices
   ```

2. **Show Order Summary Before Checkout**
   - Display subtotal, discount, tax, shipping, total
   - Show applied coupons
   - Allow coupon removal before checkout

3. **Handle Payment Redirect Gracefully**
   - Save order ID before redirect
   - Show loading state during redirect
   - Handle user returning from payment

4. **Implement Payment Success/Failure Handlers**
   ```javascript
   // Success URL: /payment/success?order_id=123
   // Failure URL: /payment/failure?order_id=123
   ```

5. **Show Order Confirmation**
   - Display order number
   - Show expected delivery date
   - Provide order tracking link

6. **Allow Payment Retry**
   - If payment_url is null
   - If user cancelled payment
   - Use PaymentController.create() endpoint

---
**Last Updated**: 2026-02-03
**API Version**: v1

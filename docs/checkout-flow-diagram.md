# Checkout Flow - Sequence Diagram

## Complete Checkout Process

```
┌─────────┐         ┌──────────┐         ┌──────────────┐         ┌──────────────┐
│ Frontend│         │Controller│         │OrderService  │         │CartService   │
└────┬────┘         └─────┬────┘         └──────┬───────┘         └──────┬───────┘
     │                    │                     │                        │
     │ POST /checkout     │                     │                        │
     ├───────────────────>│                     │                        │
     │  + addresses       │                     │                        │
     │  + shipping_cost   │                     │                        │
     │  + payment_method  │                     │                        │
     │                    │                     │                        │
     │                    │ createFromCart()    │                        │
     │                    ├────────────────────>│                        │
     │                    │                     │                        │
     │                    │                     │ validateCart()         │
     │                    │                     ├───────────────────────>│
     │                    │                     │<───────────────────────┤
     │                    │                     │ ✓ Stock, Prices OK     │
     │                    │                     │                        │
     └────┬───────┘        └─────┬────┘         └──────┬───────┘         └──────┬───────┘
          │                      │                     │                        │

┌─────────┴──────┐         ┌──────┴───────┐         ┌─────┴──────────┐
│CouponService   │         │Calculation   │         │PaymentService  │
└────────┬───────┘         │Service       │         └────────┬───────┘
         │                 └──────┬───────┘                  │
         │                        │                          │
         │ validateCoupon()       │                          │
         │<──────────────────────┤│                          │
         │ ✓ Valid                │                          │
         ├───────────────────────>││                          │
         │                        │                          │
         │ calculateDiscount()    │                          │
         │<──────────────────────┤│                          │
         │ Returns: 250.00        │                          │
         ├───────────────────────>││                          │
         │                        │                          │
         │                        │ calculate()              │
         │                        │<────────────────────────┤│
         │                        │ subtotal: 2500           │
         │                        │ discount: 250            │
         │                        │ tax: 472.50              │
         │                        │ shipping: 500            │
         │                        │ total: 3222.50           │
         │                        ├─────────────────────────>││
         │                        │                          │

         ┌──────────┐
         │ Database │
         │Transaction│
         └─────┬────┘
               │
               │ BEGIN TRANSACTION
               │
               │ 1. Create OrderAddress (shipping)
               │ 2. Create OrderAddress (billing)
               │ 3. Create Order
               │    - subtotal: 2500.00
               │    - discount: 250.00
               │    - tax: 472.50
               │    - shipping: 500.00
               │    - total: 3222.50
               │
               │ 4. Create OrderItems
               │    - Copy from cart items
               │    - Store prices, quantities
               │
               │ 5. Decrease Product Stock
               │    - For each order item
               │    - Update product/variant stock
               │
               │ 6. Record Coupon Usage
               │    - Create CouponUsage record
               │    - Increment Coupon.used_count
               │
               │ 7. Add Order Status History
               │    - "Order created"
               │

               ┌─────────────────────────────────────────┐
               │      Mercado Pago Integration           │
               └──────────────────┬──────────────────────┘
                                  │
                                  │ createPaymentPreference()
                                  │ ┌──────────────────────────┐
                                  ├>│ 1. Create Payment Record │
                                  │ │    - gateway: mercado_pago│
                                  │ │    - status: pending      │
                                  │ │    - amount: 3222.50      │
                                  │ │    - currency: ARS        │
                                  │ └──────────────────────────┘
                                  │
                                  │ ┌──────────────────────────┐
                                  ├>│ 2. Call Mercado Pago API │
                                  │ │    POST /checkout/preferences│
                                  │ │    {                      │
                                  │ │      items: [...],        │
                                  │ │      payer: {...},        │
                                  │ │      back_urls: {...},    │
                                  │ │      external_reference   │
                                  │ │    }                      │
                                  │ └──────────────────────────┘
                                  │
                                  │ ◄─ Response:
                                  │    preference_id: "xxxxx"
                                  │    init_point: "https://..."
                                  │
                                  │ ┌──────────────────────────┐
                                  └>│ 3. Update Payment Record  │
                                    │    - external_id          │
                                    │    - metadata             │
                                    └──────────────────────────┘

               │ 8. Clear Cart
               │    - Delete all cart items
               │    - Remove coupon associations
               │
               │ COMMIT TRANSACTION
               │
         ┌─────┴────┐
         │          │

     ┌────┴────────────────────────────────┐
     │   Return to Controller              │
     │   {                                 │
     │     order: Order,                   │
     │     payment_url: "https://mp.com..."│
     │   }                                 │
     └─────┬───────────────────────────────┘
           │
     ┌─────┴───────────────────────────────┐
     │   JSON Response                     │
     │   {                                 │
     │     "success": true,                │
     │     "message": "Order created",     │
     │     "data": {                       │
     │       "order": {...},               │
     │       "payment_url": "..."          │
     │     }                               │
     │   }                                 │
     └─────┬───────────────────────────────┘
           │
     ┌─────▼───────────────────────────────┐
     │   Frontend Receives Response        │
     │   - Shows order confirmation        │
     │   - Redirects to payment_url        │
     │   - User completes payment at MP    │
     └─────────────────────────────────────┘
```

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     Error Scenarios                             │
└─────────────────────────────────────────────────────────────────┘

1. Empty Cart
   ├─> throw InvalidOperationException('Cart is empty', 'EMPTY_CART')
   └─> Response: 422 Unprocessable Entity

2. Cart Validation Failed (out of stock, prices changed)
   ├─> throw InvalidOperationException('Some items unavailable', 'CART_VALIDATION_FAILED')
   └─> Response: 422 with item-specific errors

3. Invalid Coupon
   ├─> throw InvalidOperationException('Coupon no longer valid', 'INVALID_COUPON')
   └─> Response: 422 with coupon details

4. Payment Preference Creation Failed
   ├─> Log error (don't throw)
   ├─> Continue order creation
   ├─> payment_url = null
   └─> User can retry payment later via PaymentController

5. Database Transaction Failed
   ├─> Automatic rollback
   ├─> No changes committed
   ├─> Stock not decreased
   ├─> Coupons not marked as used
   └─> Error propagated to controller
```

## Data Transformations

```
Cart Item → Order Item
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
cart_items.product_id        → order_items.product_id
cart_items.variant_id        → order_items.variant_id
cart_items.quantity          → order_items.quantity
product.name                 → order_items.name
variant.sku / product.sku    → order_items.sku
cart_items.current_price     → order_items.price
cart_items.total             → order_items.total
variant.attributes           → order_items.options


Address Array → OrderAddress Model
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
shipping_address.name         → order_addresses.name
shipping_address.phone        → order_addresses.phone
shipping_address.address      → order_addresses.address
shipping_address.address_line_2 → order_addresses.address_line_2
shipping_address.city         → order_addresses.city
shipping_address.state        → order_addresses.state
shipping_address.postal_code  → order_addresses.postal_code
shipping_address.country      → order_addresses.country (default: AR)
                              → order_addresses.type = 'shipping'


Cart Coupon → CouponUsage
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
cart_coupons.coupon_id       → coupon_usages.coupon_id
cart.user_id                 → coupon_usages.user_id
order.id                     → coupon_usages.order_id
calculated_discount          → coupon_usages.discount_amount
now()                        → coupon_usages.used_at
```

## Calculation Example

```
Cart Contents:
┌──────────────────────────────────────────┐
│ Product A: $1000 x 2 = $2000            │
│ Product B: $500  x 1 = $500             │
│ ─────────────────────────────────────── │
│ Subtotal:          $2500                │
└──────────────────────────────────────────┘

Applied Coupons:
┌──────────────────────────────────────────┐
│ SAVE10: 10% discount                    │
│ Minimum: $1000 ✓                        │
│ Max uses: 100, Used: 45 ✓               │
│ Valid until: 2026-12-31 ✓               │
└──────────────────────────────────────────┘

Calculation Steps:
┌──────────────────────────────────────────┐
│ 1. Subtotal:          $2500.00          │
│ 2. Coupon Discount:   -$250.00 (10%)   │
│ 3. Taxable Amount:    $2250.00          │
│ 4. Tax (21%):         +$472.50          │
│ 5. Shipping:          +$500.00          │
│ ─────────────────────────────────────── │
│ TOTAL:                $3222.50          │
└──────────────────────────────────────────┘

Database Values:
┌──────────────────────────────────────────┐
│ orders.subtotal:       2500.00          │
│ orders.discount:       250.00           │
│ orders.tax:            472.50           │
│ orders.shipping_cost:  500.00           │
│ orders.total:          3222.50          │
└──────────────────────────────────────────┘
```

## State Transitions

```
Order Status Flow:
pending → processing → shipped → delivered
   ↓
cancelled


Payment Status Flow:
pending → paid → refunded
   ↓         ↓
failed   partially_refunded
   ↓
cancelled


Coupon State:
available → applied_to_cart → used_in_order
                ↑                  ↓
                └─────────────────┘
              (can be reused if within limits)
```

## Integration Points

```
┌──────────────────────────────────────────────────────────┐
│              External System Integrations                │
└──────────────────────────────────────────────────────────┘

1. Mercado Pago (Payment Gateway)
   ├─ Endpoint: POST /checkout/preferences/
   ├─ Auth: Access Token
   ├─ Purpose: Create payment preference
   └─ Response: preference_id, init_point

2. Mercado Pago (Webhooks)
   ├─ Endpoint: POST /api/v1/webhooks/mercadopago
   ├─ Triggered: When payment status changes
   ├─ Updates: Payment status, Order payment_status
   └─ Events: payment.created, payment.updated

3. Email Service (Future)
   ├─ Send order confirmation email
   ├─ Send payment receipt email
   └─ Send shipping notification email

4. Inventory System (Current - Internal)
   ├─ Decrease stock on order creation
   ├─ Increase stock on order cancellation
   └─ Real-time stock validation

5. Andreani API (Future - Shipping)
   ├─ Calculate real-time shipping quotes
   ├─ Create shipping labels
   └─ Track shipments
```

---
**Last Updated**: 2026-02-03
**Status**: Complete

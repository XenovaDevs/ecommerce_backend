# API Usage Examples

Ejemplos de uso de la API del ecommerce backend.

## Base URL

```
http://localhost:8000/api/v1
```

## Authentication

### Register

```bash
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "customer"
    },
    "access_token": "1|...",
    "token_type": "Bearer",
    "expires_in": 1440
  }
}
```

### Login

```bash
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "2|...",
    "refresh_token": "abc123...",
    "token_type": "Bearer",
    "expires_in": 1440
  }
}
```

### Get Current User

```bash
GET /auth/me
Authorization: Bearer {access_token}
```

### Logout

```bash
POST /auth/logout
Authorization: Bearer {access_token}
```

### Refresh Token

```bash
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "abc123..."
}
```

## Categories

### List All Categories

```bash
GET /categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Electronics",
      "slug": "electronics",
      "description": "Electronic devices",
      "image": null,
      "parent_id": null,
      "position": 1,
      "is_active": true,
      "children": []
    }
  ]
}
```

### Get Category by Slug

```bash
GET /categories/electronics
```

## Products

### List Products

```bash
GET /products?page=1&per_page=15&search=phone&category_id=1&min_price=100&max_price=1000&sort_by=price&sort_order=asc
```

**Query Parameters:**
- `search` - Search term
- `category_id` - Filter by category
- `min_price` - Minimum price
- `max_price` - Maximum price
- `is_featured` - Featured products only
- `sort_by` - Sort field (name, price, created_at)
- `sort_order` - Sort order (asc, desc)
- `per_page` - Items per page (max 100)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "slug": "iphone-15-pro",
      "description": "Latest iPhone",
      "price": 999.99,
      "sale_price": 899.99,
      "stock": 50,
      "is_featured": true,
      "primary_image": {
        "url": "https://example.com/image.jpg"
      },
      "category": {
        "id": 1,
        "name": "Smartphones"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "total_pages": 7,
    "has_more": true
  }
}
```

### Get Featured Products

```bash
GET /products/featured
```

### Get Product by Slug

```bash
GET /products/iphone-15-pro
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "iPhone 15 Pro",
    "slug": "iphone-15-pro",
    "description": "Full description...",
    "short_description": "Short description",
    "price": 999.99,
    "sale_price": 899.99,
    "sku": "IPH15PRO",
    "stock": 50,
    "is_featured": true,
    "is_active": true,
    "weight": 0.5,
    "category": {...},
    "images": [
      {
        "id": 1,
        "url": "https://example.com/image1.jpg",
        "is_primary": true,
        "position": 0
      }
    ],
    "variants": [
      {
        "id": 1,
        "sku": "IPH15PRO-BLK-256",
        "price": 999.99,
        "stock": 20,
        "attributes": {
          "color": "Black",
          "storage": "256GB"
        }
      }
    ]
  }
}
```

## Cart

### Get Current Cart

```bash
GET /cart
Authorization: Bearer {access_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "items": [
      {
        "id": 1,
        "product": {
          "id": 1,
          "name": "iPhone 15 Pro",
          "slug": "iphone-15-pro"
        },
        "quantity": 2,
        "price": 899.99,
        "subtotal": 1799.98
      }
    ],
    "subtotal": 1799.98,
    "tax": 179.99,
    "total": 1979.97,
    "items_count": 2
  }
}
```

### Add Item to Cart

```bash
POST /cart/items
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "product_id": 1,
  "variant_id": null,
  "quantity": 1
}
```

### Update Cart Item

```bash
PUT /cart/items/1
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "quantity": 3
}
```

### Remove Item from Cart

```bash
DELETE /cart/items/1
Authorization: Bearer {access_token}
```

### Clear Cart

```bash
DELETE /cart/clear
Authorization: Bearer {access_token}
```

## Orders

### Get User Orders

```bash
GET /orders
Authorization: Bearer {access_token}
```

### Get Order Details

```bash
GET /orders/123
Authorization: Bearer {access_token}
```

### Checkout (Create Order)

```bash
POST /checkout
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "shipping_address": {
    "name": "John Doe",
    "phone": "+1234567890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "USA"
  },
  "billing_address": {
    "name": "John Doe",
    "phone": "+1234567890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "USA"
  },
  "notes": "Please deliver after 5pm",
  "payment_method": "mercadopago"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "order_number": "ORD-2024-0001",
      "status": "pending",
      "payment_status": "pending",
      "total": 1979.97
    },
    "payment_url": "https://mercadopago.com/checkout/..."
  }
}
```

### Cancel Order

```bash
POST /orders/1/cancel
Authorization: Bearer {access_token}
```

## Customer Profile

### Get Profile

```bash
GET /customer/profile
Authorization: Bearer {access_token}
```

### Update Profile

```bash
PUT /customer/profile
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "John Doe Updated",
  "phone": "+9876543210"
}
```

## Customer Addresses

### List Addresses

```bash
GET /customer/addresses
Authorization: Bearer {access_token}
```

### Create Address

```bash
POST /customer/addresses
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "label": "Home",
  "name": "John Doe",
  "phone": "+1234567890",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "USA",
  "is_default": true
}
```

### Update Address

```bash
PUT /customer/addresses/1
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "label": "Work",
  "is_default": false
}
```

### Delete Address

```bash
DELETE /customer/addresses/1
Authorization: Bearer {access_token}
```

## Wishlist

### Get Wishlist

```bash
GET /wishlist
Authorization: Bearer {access_token}
```

### Add to Wishlist

```bash
POST /wishlist
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "product_id": 1
}
```

### Remove from Wishlist

```bash
DELETE /wishlist/1
Authorization: Bearer {access_token}
```

## Payments

### Create Payment Preference

Crea una preferencia de pago en Mercado Pago y obtiene el link de checkout.

```bash
POST /payments/create
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "order_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "payment_id": 123,
    "preference_id": "1234567890-abcd1234-ef56-7890-ghij-1234567890ab",
    "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1234567890...",
    "sandbox_init_point": "https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1234567890..."
  }
}
```

El cliente debe ser redirigido al `init_point` (o `sandbox_init_point` en desarrollo) para completar el pago.

### Check Payment Status

Consulta el estado actual de un pago. Use `sync=true` para sincronizar con Mercado Pago.

```bash
GET /payments/123/status?sync=true
Authorization: Bearer {access_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "order_id": 1,
    "status": "paid",
    "amount": 15999.99,
    "currency": "ARS",
    "gateway": "mercado_pago",
    "external_id": "1234567890-abcd1234-ef56-7890-ghij-1234567890ab",
    "created_at": "2025-01-31T10:30:00Z",
    "updated_at": "2025-01-31T10:35:00Z"
  }
}
```

**Payment Status Values:**
- `pending`: Pago pendiente
- `processing`: Procesando
- `paid`: Pagado exitosamente
- `approved`: Aprobado
- `failed`: Fallido
- `rejected`: Rechazado
- `cancelled`: Cancelado
- `refunded`: Reembolsado

### Webhook (Mercado Pago)

Mercado Pago enviará notificaciones POST a `/api/v1/webhooks/mercadopago` cuando cambie el estado de un pago.

**Configuración en Mercado Pago:**
1. Ingresar a la cuenta de Mercado Pago
2. Ir a "Tu integración" > "Webhooks"
3. Agregar URL: `https://tu-dominio.com/api/v1/webhooks/mercadopago`
4. Seleccionar eventos: "Pagos"

**Estructura del Webhook:**
```json
{
  "id": 12345,
  "live_mode": true,
  "type": "payment",
  "date_created": "2025-01-31T10:35:00Z",
  "application_id": "1234567890",
  "user_id": "987654321",
  "version": 1,
  "api_version": "v1",
  "action": "payment.updated",
  "data": {
    "id": "1234567890"
  }
}
```

**Headers de Seguridad:**
- `x-signature`: Firma HMAC-SHA256 del webhook
- `x-request-id`: ID único de la petición

El webhook está protegido con validación de firma usando `MERCADOPAGO_WEBHOOK_SECRET`.

## Shipping

### Quote Shipping

```bash
POST /shipping/quote
Content-Type: application/json

{
  "postal_code": "10001",
  "weight": 1.5,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

## Admin Endpoints

All admin endpoints require `Authorization: Bearer {admin_access_token}` header.

### Dashboard Statistics

```bash
GET /admin/dashboard
Authorization: Bearer {admin_token}
```

### Admin - Categories

```bash
# List
GET /admin/categories

# Create
POST /admin/categories
Content-Type: application/json
{
  "name": "New Category",
  "slug": "new-category",
  "description": "Description",
  "is_active": true
}

# Show
GET /admin/categories/1

# Update
PUT /admin/categories/1
Content-Type: application/json
{
  "name": "Updated Category"
}

# Delete
DELETE /admin/categories/1
```

### Admin - Products

```bash
# List
GET /admin/products

# Create
POST /admin/products
Content-Type: application/json
{
  "name": "New Product",
  "price": 99.99,
  "stock": 100,
  "category_id": 1
}

# Show
GET /admin/products/1

# Update
PUT /admin/products/1

# Delete
DELETE /admin/products/1

# Upload Image
POST /admin/products/1/images
Content-Type: application/json
{
  "url": "https://example.com/image.jpg",
  "is_primary": true
}

# Delete Image
DELETE /admin/products/1/images/1
```

### Admin - Orders

```bash
# List
GET /admin/orders?status=pending&search=ORD-2024

# Show
GET /admin/orders/1

# Update Status
PUT /admin/orders/1/status
Content-Type: application/json
{
  "status": "processing",
  "notes": "Order is being processed"
}
```

### Admin - Customers

```bash
# List
GET /admin/customers?search=john&is_active=true

# Show
GET /admin/customers/1
```

### Admin - Settings

```bash
# List All
GET /admin/settings

# Get Single
GET /admin/settings/site_name

# Update Multiple
PUT /admin/settings
Content-Type: application/json
{
  "settings": [
    {
      "key": "site_name",
      "value": "My Ecommerce"
    },
    {
      "key": "currency",
      "value": "USD"
    }
  ]
}

# Create
POST /admin/settings
Content-Type: application/json
{
  "key": "new_setting",
  "value": "value",
  "type": "string",
  "group": "general",
  "is_public": false
}

# Delete
DELETE /admin/settings/setting_key
```

### Admin - Reports

```bash
# Sales Report
GET /admin/reports/sales?start_date=2024-01-01&end_date=2024-12-31

# Products Report
GET /admin/reports/products?start_date=2024-01-01&limit=20

# Customers Report
GET /admin/reports/customers?start_date=2024-01-01
```

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message",
    "details": {}
  },
  "meta": {
    "timestamp": "2024-01-01T12:00:00Z",
    "request_id": "abc-123"
  }
}
```

Common HTTP Status Codes:
- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

## Rate Limiting

- Default: 60 requests per minute
- Auth endpoints: 5 requests per minute
- Checkout: 10 requests per minute

When rate limited, you'll receive a `429 Too Many Requests` response with headers:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `Retry-After`

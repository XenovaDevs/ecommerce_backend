# Sistema de Cupones - Documentación

## Descripción General

El sistema de cupones permite aplicar descuentos a carritos de compras mediante códigos promocionales. Está diseñado siguiendo principios SOLID y Clean Code, con separación clara de responsabilidades.

## Arquitectura

### Principios SOLID Aplicados

#### Single Responsibility Principle (SRP)
- **CouponService**: Maneja únicamente la lógica de negocio de cupones
- **CouponController**: Solo se encarga de recibir requests y retornar responses
- **InvalidCouponException**: Representa errores de validación de cupones
- **CouponAlreadyAppliedException**: Representa error específico de cupón duplicado

#### Open/Closed Principle (OCP)
- El sistema está abierto para extensión (nuevos tipos de validación) pero cerrado para modificación
- Se pueden agregar nuevos tipos de cupones sin modificar código existente

#### Liskov Substitution Principle (LSP)
- Todas las excepciones extienden `BaseException` y pueden ser sustituidas sin alterar el comportamiento

#### Interface Segregation Principle (ISP)
- Los servicios exponen solo los métodos necesarios para su propósito específico

#### Dependency Inversion Principle (DIP)
- El controlador depende de abstracciones (servicios inyectados) no de implementaciones concretas

## Componentes

### 1. CouponService
**Ubicación**: `app/Services/Coupon/CouponService.php`

**Métodos principales**:

```php
validateCoupon(string $code, float $amount): Coupon
```
Valida un cupón contra múltiples reglas de negocio:
- Código válido
- Cupón activo
- No expirado
- No excede usos máximos
- Monto mínimo cumplido

```php
applyCouponToCart(Cart $cart, string $code): void
```
Aplica un cupón validado a un carrito, verificando que no esté duplicado.

```php
removeCouponFromCart(Cart $cart, int $couponId): void
```
Remueve un cupón del carrito.

```php
recordCouponUsage(Coupon $coupon, User $user, Order $order, float $discountAmount): CouponUsage
```
Registra el uso de un cupón cuando se completa una orden.

```php
calculateCartDiscount(Cart $cart): float
```
Calcula el descuento total aplicando cupones secuencialmente.

### 2. Excepciones

#### InvalidCouponException
**Ubicación**: `app/Exceptions/Coupon/InvalidCouponException.php`

**HTTP Status**: 422 Unprocessable Entity

**Códigos de error**:
- `COUPON_NOT_FOUND`: Código no existe
- `COUPON_INACTIVE`: Cupón desactivado
- `COUPON_NOT_STARTED`: Aún no es válido
- `COUPON_EXPIRED`: Expirado
- `COUPON_MAX_USES_REACHED`: Límite de usos alcanzado
- `COUPON_MINIMUM_AMOUNT_NOT_REACHED`: Monto mínimo no alcanzado
- `COUPON_NOT_APPLIED_TO_CART`: Cupón no está aplicado al carrito

#### CouponAlreadyAppliedException
**Ubicación**: `app/Exceptions/Coupon/CouponAlreadyAppliedException.php`

**HTTP Status**: 409 Conflict

**Código de error**: `COUPON_ALREADY_APPLIED`

### 3. CouponController
**Ubicación**: `app/Http/Controllers/Api/V1/CouponController.php`

Controlador REST que sigue el patrón "thin controller". Toda la lógica de negocio está delegada a los servicios.

### 4. ApplyCouponRequest
**Ubicación**: `app/Http/Requests/Coupon/ApplyCouponRequest.php`

Validación de entrada para aplicar cupones:
- `code`: requerido, string, máximo 50 caracteres, formato alfanumérico con guiones y guiones bajos

## API Endpoints

### Aplicar Cupón
```
POST /api/v1/cart/coupons
```

**Headers**:
```
X-Session-ID: <session-id>  (para carritos guest)
Authorization: Bearer <token>  (opcional, para usuarios autenticados)
```

**Request Body**:
```json
{
  "code": "SUMMER2024"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Coupon applied successfully",
  "data": {
    "cart": {
      "id": 1,
      "items": [...],
      "subtotal": 100.00,
      "discount": 15.00,
      "tax": 0.00,
      "total": 85.00,
      "coupons": [
        {
          "id": 1,
          "code": "SUMMER2024",
          "type": "percentage",
          "value": 15.0,
          "discount_amount": 15.00
        }
      ]
    },
    "discount": 15.00
  }
}
```

**Response Error (422)**:
```json
{
  "success": false,
  "error": {
    "code": "COUPON_EXPIRED",
    "message": "This coupon has expired"
  },
  "meta": {
    "timestamp": "2024-02-03T23:45:00Z",
    "request_id": "abc123"
  }
}
```

**Response Error (409)**:
```json
{
  "success": false,
  "error": {
    "code": "COUPON_ALREADY_APPLIED",
    "message": "This coupon has already been applied to your cart"
  },
  "meta": {
    "timestamp": "2024-02-03T23:45:00Z",
    "request_id": "abc123"
  }
}
```

### Remover Cupón
```
DELETE /api/v1/cart/coupons/{coupon_id}
```

**Headers**:
```
X-Session-ID: <session-id>  (para carritos guest)
Authorization: Bearer <token>  (opcional, para usuarios autenticados)
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Coupon removed successfully",
  "data": {
    "cart": {
      "id": 1,
      "items": [...],
      "subtotal": 100.00,
      "discount": 0.00,
      "tax": 0.00,
      "total": 100.00,
      "coupons": []
    },
    "discount": 0.00
  }
}
```

## Reglas de Negocio

### Validación de Cupones

1. **Código válido**: El cupón debe existir en la base de datos
2. **Activo**: `is_active = true`
3. **Fecha de inicio**: `starts_at` debe ser null o en el pasado
4. **Fecha de expiración**: `expires_at` debe ser null o en el futuro
5. **Usos máximos**: Si `max_uses` está definido, `used_count` debe ser menor
6. **Monto mínimo**: Si `minimum_amount` está definido, el subtotal del carrito debe cumplirlo

### Aplicación de Cupones

- No se permiten cupones duplicados en el mismo carrito (unique constraint en DB)
- Los cupones se aplican secuencialmente al calcular descuentos
- Cada cupón subsecuente se aplica sobre el monto restante después de descuentos previos

### Tipos de Cupones

**Fixed (Descuento fijo)**:
```php
$discount = min($coupon->value, $amount);
```
Descuenta un valor fijo (ej: $10 de descuento) sin exceder el monto del carrito.

**Percentage (Porcentaje)**:
```php
$discount = round($amount * ($coupon->value / 100), 2);
```
Descuenta un porcentaje del monto (ej: 15% de descuento).

## Flujo de Checkout

Cuando se completa una orden, el sistema debe:

1. Obtener los cupones aplicados al carrito
2. Calcular el descuento total
3. Crear la orden con el descuento aplicado
4. Para cada cupón usado, llamar a:
```php
$couponService->recordCouponUsage($coupon, $user, $order, $discountAmount);
```

Esto:
- Crea un registro en `coupon_usage` para tracking
- Incrementa el contador `used_count` del cupón
- Se ejecuta en una transacción para garantizar consistencia

## Base de Datos

### Tabla: cart_coupons (pivot)
```sql
CREATE TABLE cart_coupons (
  id INTEGER PRIMARY KEY,
  cart_id INTEGER NOT NULL,
  coupon_id INTEGER NOT NULL,
  created_at DATETIME,
  updated_at DATETIME,
  UNIQUE(cart_id, coupon_id),  -- Previene duplicados
  FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
);
```

### Relaciones en Eloquent

**Cart Model**:
```php
public function coupons(): BelongsToMany
{
    return $this->belongsToMany(Coupon::class, 'cart_coupons');
}
```

**Coupon Model**:
```php
public function usage(): HasMany
{
    return $this->hasMany(CouponUsage::class);
}
```

## Testing

### Casos de Prueba Recomendados

1. **Aplicar cupón válido**: Debe aplicarse correctamente y calcular descuento
2. **Cupón inválido**: Debe retornar error 422 con código específico
3. **Cupón duplicado**: Debe retornar error 409
4. **Monto mínimo no alcanzado**: Debe retornar error 422
5. **Cupón expirado**: Debe retornar error 422
6. **Remover cupón aplicado**: Debe remover y recalcular totales
7. **Remover cupón no aplicado**: Debe retornar error
8. **Múltiples cupones**: Debe aplicarlos secuencialmente
9. **Registro de uso**: Debe incrementar contador y crear registro

### Ejemplo de Test
```php
public function test_apply_valid_coupon_to_cart()
{
    $coupon = Coupon::factory()->create([
        'code' => 'TEST15',
        'type' => 'percentage',
        'value' => 15,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/cart/coupons', [
        'code' => 'TEST15'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'cart' => ['id', 'subtotal', 'discount', 'total', 'coupons'],
                'discount'
            ]
        ]);
}
```

## Extensibilidad

### Agregar Nuevo Tipo de Validación

Extender `validateCoupon()` en `CouponService`:

```php
// Validar uso por usuario
if ($coupon->max_uses_per_user) {
    $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
        ->where('user_id', $userId)
        ->count();

    if ($userUsageCount >= $coupon->max_uses_per_user) {
        throw new InvalidCouponException(
            'You have already used this coupon',
            'COUPON_USER_LIMIT_REACHED'
        );
    }
}
```

### Agregar Nuevo Tipo de Cupón

Extender `calculateDiscount()` en el modelo `Coupon`:

```php
public function calculateDiscount(float $amount): float
{
    return match($this->type) {
        'fixed' => min($this->value, $amount),
        'percentage' => round($amount * ($this->value / 100), 2),
        'buy_x_get_y' => $this->calculateBuyXGetY($amount),
        default => 0.0,
    };
}
```

## Seguridad

### Consideraciones

1. **Rate Limiting**: Considerar limitar requests a endpoints de cupones para prevenir fuerza bruta
2. **Validación de Input**: El `ApplyCouponRequest` valida formato y longitud
3. **Autorización**: Los cupones pueden aplicarse tanto a carritos guest como autenticados
4. **Transacciones**: El registro de uso usa transacciones DB para consistencia
5. **Metadata Filtering**: Las excepciones filtran información sensible antes de ser retornadas

## Mejoras Futuras

- [ ] Cupones exclusivos para usuarios específicos
- [ ] Cupones con límite por usuario
- [ ] Cupones aplicables solo a categorías/productos específicos
- [ ] Cupones "buy X get Y"
- [ ] Sistema de referidos con cupones automáticos
- [ ] Dashboard de analytics de cupones
- [ ] Pruebas A/B de cupones

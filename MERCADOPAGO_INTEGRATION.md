# Integración con Mercado Pago

Documentación completa de la integración con Mercado Pago para el ecommerce backend.

## Tabla de Contenidos

- [Arquitectura](#arquitectura)
- [Configuración](#configuración)
- [Flujo de Pago](#flujo-de-pago)
- [Webhooks](#webhooks)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Arquitectura

La integración sigue los principios SOLID y Clean Code, con separación clara de responsabilidades:

### Componentes Principales

#### 1. MercadoPagoService (`app/Services/Payment/MercadoPagoService.php`)

**Responsabilidad Única:** Encapsula todas las interacciones con el SDK de Mercado Pago.

- Configuración del SDK
- Creación de preferencias de pago
- Consulta de información de pagos
- Validación de firma de webhooks
- Mapeo de estados de pago

#### 2. PaymentService (`app/Services/Payment/PaymentService.php`)

**Responsabilidad Única:** Orquesta el proceso de pago y gestiona el modelo de dominio Payment.

- Validación de órdenes
- Creación de registros de pago
- Sincronización con gateway
- Procesamiento de webhooks
- Actualización de órdenes según estado de pago

#### 3. WebhookValidator (`app/Services/Payment/WebhookValidator.php`)

**Responsabilidad Única:** Valida la autenticidad de las peticiones webhook.

- Verificación de firma HMAC-SHA256
- Validación de headers de seguridad

#### 4. WebhookController (`app/Http/Controllers/Api/V1/WebhookController.php`)

**Responsabilidad Única:** Recibe webhooks externos y delega el procesamiento.

- Validación de webhooks
- Logging de peticiones
- Manejo de errores

### Principios Aplicados

**Single Responsibility Principle (SRP)**
- Cada clase tiene una única razón para cambiar
- MercadoPagoService solo conoce del SDK
- PaymentService solo gestiona la lógica de negocio de pagos
- WebhookValidator solo valida firmas

**Open/Closed Principle (OCP)**
- El sistema está abierto a extensión (agregar nuevos gateways)
- Cerrado a modificación (no requiere cambiar código existente)

**Dependency Inversion Principle (DIP)**
- PaymentService depende de MercadoPagoService vía inyección de dependencias
- No hay acoplamiento fuerte con implementaciones concretas

**Interface Segregation Principle (ISP)**
- Métodos pequeños y enfocados
- Clientes no dependen de métodos que no usan

**Liskov Substitution Principle (LSP)**
- Enums y eventos son sustituibles sin romper el contrato

## Configuración

### 1. Instalar Dependencias

El SDK de Mercado Pago ya está instalado:

```bash
composer require mercadopago/dx-php
```

### 2. Variables de Entorno

Configurar en `.env`:

```env
# Frontend URL
FRONTEND_URL=http://localhost:3000

# Mercado Pago Configuration
MERCADOPAGO_PUBLIC_KEY=TEST-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MERCADOPAGO_ACCESS_TOKEN=TEST-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx-xxxxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx
MERCADOPAGO_WEBHOOK_SECRET=tu_webhook_secret_aqui
```

### 3. Obtener Credenciales

1. Crear cuenta en [Mercado Pago Developers](https://www.mercadopago.com.ar/developers)
2. Ir a "Tus integraciones" > "Credenciales"
3. Copiar "Public Key" y "Access Token"
4. Para testing, usar credenciales de TEST
5. Para producción, usar credenciales de PRODUCCIÓN

### 4. Configurar Webhook Secret

El webhook secret se genera automáticamente en Mercado Pago. Para mayor seguridad:

1. Generar un secret personalizado: `openssl rand -base64 32`
2. Configurar en Mercado Pago (opcional)
3. Guardar en `MERCADOPAGO_WEBHOOK_SECRET`

Si no se configura un secret, la validación se omitirá (NO recomendado para producción).

## Flujo de Pago

### 1. Cliente Crea una Orden

```http
POST /api/v1/checkout
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "shipping_address_id": 1,
  "billing_address_id": 1,
  "notes": "Entregar en horario laboral"
}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "order_number": "ORD-250131-XY7Z",
    "status": "pending",
    "payment_status": "pending",
    "total": 15999.99
  }
}
```

### 2. Cliente Solicita Preferencia de Pago

```http
POST /api/v1/payments/create
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "order_id": 123
}
```

**Proceso Interno:**
1. `PaymentService::createPaymentPreference()` valida la orden
2. Crea un registro `Payment` con estado `pending`
3. Delega a `MercadoPagoService::createPreference()`
4. El SDK crea la preferencia en Mercado Pago
5. Retorna el `init_point` para redirección

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "payment_id": 456,
    "preference_id": "1234567890-abcd1234-ef56-7890-ghij-1234567890ab",
    "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=...",
    "sandbox_init_point": "https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=..."
  }
}
```

### 3. Cliente es Redirigido a Mercado Pago

El frontend redirige al usuario a `init_point` (producción) o `sandbox_init_point` (testing).

### 4. Usuario Completa el Pago en Mercado Pago

El usuario ingresa datos de pago en el checkout de Mercado Pago.

### 5. Mercado Pago Notifica vía Webhook

Cuando el pago cambia de estado, Mercado Pago envía un POST a:

```
POST /api/v1/webhooks/mercadopago
```

**Proceso Interno:**
1. `WebhookController` recibe la petición
2. `WebhookValidator` verifica la firma HMAC-SHA256
3. Si es válido, `PaymentService::processWebhook()` procesa
4. `MercadoPagoService::getPayment()` consulta el pago
5. Actualiza `Payment` y `Order` según el estado
6. Si el pago es exitoso, dispara evento `OrderPaid`

### 6. Usuario es Redirigido de Vuelta

Mercado Pago redirige al usuario según el resultado:

- **Éxito:** `FRONTEND_URL/checkout/success?payment_id=456`
- **Fallo:** `FRONTEND_URL/checkout/failure?payment_id=456`
- **Pendiente:** `FRONTEND_URL/checkout/pending?payment_id=456`

### 7. Frontend Consulta Estado

```http
GET /api/v1/payments/456/status?sync=true
Authorization: Bearer {access_token}
```

El parámetro `sync=true` fuerza sincronización con Mercado Pago.

## Webhooks

### Configurar Webhooks en Mercado Pago

1. Ir a [Webhooks en Mercado Pago](https://www.mercadopago.com.ar/developers/panel/webhooks)
2. Agregar URL: `https://tu-dominio.com/api/v1/webhooks/mercadopago`
3. Seleccionar eventos: **Pagos**
4. Guardar

### Estructura del Webhook

Mercado Pago envía:

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

### Headers de Seguridad

Mercado Pago incluye headers para validación:

- **x-signature:** Firma HMAC-SHA256
- **x-request-id:** ID único de la petición

**Formato de x-signature:**
```
ts=1612137942,v1=3d7e3a8c5f1b2e4d6c8a9f0e1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c
```

### Validación de Firma

El webhook secret se usa para calcular HMAC-SHA256:

```
manifest = timestamp + request_id + raw_body
expected_signature = HMAC-SHA256(manifest, webhook_secret)
```

Si las firmas coinciden, el webhook es válido.

### Estados de Pago

| Estado MP | Estado App | Descripción |
|-----------|------------|-------------|
| `approved` | `paid` | Pago aprobado exitosamente |
| `pending` | `pending` | Pago pendiente de confirmación |
| `in_process` | `pending` | Pago en proceso |
| `rejected` | `failed` | Pago rechazado |
| `cancelled` | `cancelled` | Pago cancelado |
| `refunded` | `refunded` | Pago reembolsado |

### Eventos Disparados

Cuando un pago es exitoso, se dispara:

```php
OrderPaid::dispatch($order, $transactionId);
```

Este evento:
- Se transmite vía WebSocket a los canales:
  - `orders.{user_id}` (privado del usuario)
  - `admin.orders` (privado de admins)
- Permite ejecutar acciones post-pago:
  - Enviar email de confirmación
  - Actualizar inventario
  - Generar factura
  - Notificar al área de logística

## Testing

### Testing Local con ngrok

Para recibir webhooks en desarrollo local:

1. Instalar ngrok: https://ngrok.com/
2. Ejecutar: `ngrok http 8000`
3. Copiar URL pública: `https://abcd1234.ngrok.io`
4. Configurar en Mercado Pago: `https://abcd1234.ngrok.io/api/v1/webhooks/mercadopago`

### Credenciales de Test

Usar credenciales con prefijo `TEST-` en `.env`:

```env
MERCADOPAGO_ACCESS_TOKEN=TEST-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx-xxxxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx
```

### Tarjetas de Prueba

Para probar pagos en sandbox:

**Tarjeta Aprobada:**
```
Número: 5031 7557 3453 0604
CVV: 123
Fecha: 11/25
Nombre: APRO
```

**Tarjeta Rechazada:**
```
Número: 5031 4332 1540 6351
CVV: 123
Fecha: 11/25
Nombre: OTHE
```

Más tarjetas de prueba: https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/additional-content/test-cards

### Testing Manual

1. Crear una orden
2. Crear preferencia de pago
3. Abrir `sandbox_init_point` en el navegador
4. Completar con tarjeta de prueba
5. Verificar webhook en logs: `php artisan pail`
6. Verificar estado de pago en DB

### Logs

Todos los pasos están logueados:

```bash
php artisan pail --filter=mercado
```

Ver logs específicos:
- `Creating Mercado Pago preference`
- `Mercado Pago preference created successfully`
- `Received Mercado Pago webhook`
- `Payment status updated`
- `Order marked as paid`

## Troubleshooting

### Error: "Mercado Pago access token is not configured"

**Causa:** Falta `MERCADOPAGO_ACCESS_TOKEN` en `.env`

**Solución:**
1. Verificar que existe en `.env`
2. Ejecutar: `php artisan config:clear`
3. Reiniciar servidor

### Error: "Failed to create payment preference"

**Causa:** Error en la API de Mercado Pago

**Diagnóstico:**
1. Verificar logs: `php artisan pail`
2. Revisar credenciales (TEST vs PROD)
3. Verificar formato de items

**Solución:**
- Verificar que `unit_price` es numérico
- Verificar que `quantity` es entero positivo
- Verificar que `currency_id` es válido (ARS, BRL, etc.)

### Error: "Webhook signature validation failed"

**Causa:** Secret incorrecto o headers faltantes

**Diagnóstico:**
1. Verificar que webhook viene de Mercado Pago
2. Revisar logs de validación

**Solución:**
- Si es en desarrollo y no importa seguridad, dejar `MERCADOPAGO_WEBHOOK_SECRET` vacío
- Verificar que el secret coincide con el configurado en MP
- Verificar que ngrok no está modificando headers

### Webhook no llega

**Causa:** URL inaccesible o no configurada

**Diagnóstico:**
1. Verificar URL en panel de Mercado Pago
2. Verificar que el servidor es accesible públicamente
3. Revisar logs de firewall/proxy

**Solución:**
- Usar ngrok en desarrollo
- Verificar certificado SSL en producción
- Verificar que la ruta `/api/v1/webhooks/mercadopago` existe

### Pago queda en "pending" indefinidamente

**Causa:** Webhook no procesado o error en procesamiento

**Diagnóstico:**
1. Revisar logs: `php artisan pail --filter=webhook`
2. Consultar manualmente: `GET /payments/{id}/status?sync=true`

**Solución:**
- Forzar sincronización con `sync=true`
- Revisar estado real en panel de Mercado Pago
- Procesar manualmente si es necesario

## Seguridad

### Mejores Prácticas Implementadas

1. **Validación de firma en webhooks** - Previene webhooks falsos
2. **Validación de ownership** - Usuario solo puede pagar sus propias órdenes
3. **Transacciones de DB** - Consistencia en actualizaciones
4. **Logging exhaustivo** - Auditoría completa
5. **Manejo de errores** - Sin exponer detalles internos
6. **Idempotencia** - Webhooks duplicados no causan problemas

### Recomendaciones Adicionales

1. **Usar HTTPS en producción** - Obligatorio para webhooks
2. **Rotar webhook secret periódicamente**
3. **Monitorear intentos de webhook fallidos**
4. **Configurar rate limiting en webhooks**
5. **Validar montos antes de confirmar órdenes**

## Migración a Producción

### Checklist

- [ ] Obtener credenciales de PRODUCCIÓN
- [ ] Actualizar `MERCADOPAGO_ACCESS_TOKEN` (sin TEST-)
- [ ] Actualizar `MERCADOPAGO_PUBLIC_KEY` (sin TEST-)
- [ ] Configurar webhook URL con HTTPS
- [ ] Configurar `MERCADOPAGO_WEBHOOK_SECRET`
- [ ] Actualizar `FRONTEND_URL` a dominio real
- [ ] Probar flujo completo en staging
- [ ] Verificar certificado SSL válido
- [ ] Configurar monitoreo de webhooks
- [ ] Documentar proceso de soporte

## Soporte

### Logs Importantes

Todos los logs incluyen contexto relevante:

```php
Log::info('Payment preference created successfully', [
    'payment_id' => $payment->id,
    'order_id' => $order->id,
    'preference_id' => $preferenceResponse['id'],
]);
```

### Contacto Mercado Pago

- Documentación: https://www.mercadopago.com.ar/developers
- Soporte: https://www.mercadopago.com.ar/developers/panel/support
- Status: https://status.mercadopago.com/

## Referencias

- [SDK PHP de Mercado Pago](https://github.com/mercadopago/sdk-php)
- [Documentación Checkout Pro](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/landing)
- [Webhooks](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks)
- [Tarjetas de Prueba](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/additional-content/test-cards)

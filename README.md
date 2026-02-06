# Ecommerce Backend - Laravel 12

Backend API completo para un sistema de ecommerce con arquitectura limpia, siguiendo principios SOLID y mejores prácticas de seguridad.

## Stack Tecnológico

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Database:** MySQL/MariaDB
- **Cache/Queue:** Redis
- **Authentication:** Laravel Sanctum
- **Testing:** Pest
- **Payment Gateway:** Mercado Pago
- **Shipping:** Andreani

## Características Principales

### Módulos Implementados

- ✅ **Authentication** - Registro, login, logout, refresh tokens, password reset
- ✅ **Settings** - Configuración flexible por tenant
- ✅ **Categories** - Categorías jerárquicas de productos
- ✅ **Products** - CRUD de productos con variantes e imágenes
- ✅ **Cart** - Carrito de compras persistente con cupones
- ✅ **Coupons** - Sistema de cupones con validaciones (fixed/percentage)
- ✅ **Orders** - Sistema completo de pedidos con impuestos
- ✅ **Payments** - Integración con Mercado Pago
- ✅ **Shipping** - Integración con Andreani
- ✅ **Reviews** - Sistema de reseñas con verificación de compra
- ✅ **Customers** - Gestión de clientes y direcciones
- ✅ **Admin** - Panel administrativo completo
- ✅ **Wishlist** - Lista de deseos

## Instalación

### Requisitos Previos

- PHP 8.2 o superior
- Composer
- MySQL/MariaDB
- Redis

### Instalación Manual

```bash
# Clonar el repositorio
git clone <repository-url>
cd ecommerce_backend

# Opción 1: Usar script de inicialización
# Windows:
init-dev.bat

# Linux/Mac:
bash init-dev.sh

# Opción 2: Manual
composer install
cp .env.example .env
php artisan key:generate
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan storage:link

# Configurar base de datos en .env
# DB_DATABASE=ecommerce
# DB_USERNAME=root
# DB_PASSWORD=

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Iniciar servidor de desarrollo
php artisan serve
```

### Instalación con Docker

```bash
# Clonar el repositorio
git clone <repository-url>
cd ecommerce_backend

# Configurar .env
cp .env.example .env

# Ajustar variables para Docker
# DB_HOST=db
# DB_DATABASE=ecommerce
# DB_USERNAME=ecommerce
# DB_PASSWORD=root
# REDIS_HOST=redis

# Iniciar contenedores
docker-compose up -d

# Instalar dependencias
docker-compose exec app composer install

# Generar key
docker-compose exec app php artisan key:generate

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Ejecutar seeders
docker-compose exec app php artisan db:seed

# La API estará disponible en: http://localhost:8000
```

### Configuración de Redis

Asegúrate de tener Redis instalado y corriendo:

```bash
# En .env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Configuración de Servicios Externos

#### Mercado Pago

```bash
MERCADOPAGO_PUBLIC_KEY=your_public_key
MERCADOPAGO_ACCESS_TOKEN=your_access_token
MERCADOPAGO_WEBHOOK_SECRET=your_webhook_secret
```

#### Andreani

```bash
ANDREANI_USERNAME=your_username
ANDREANI_PASSWORD=your_password
ANDREANI_CONTRACT_NUMBER=your_contract_number
```

## Uso

### Iniciar Servidor

```bash
php artisan serve
# API disponible en: http://localhost:8000
```

### Iniciar Queue Worker

```bash
php artisan queue:work redis
```

### Iniciar WebSocket Server (Laravel Reverb)

```bash
php artisan reverb:start
```

## API Endpoints

### Autenticación

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
```

### Productos (Público)

```
GET    /api/v1/products
GET    /api/v1/products/featured
GET    /api/v1/products/{slug}
```

### Categorías

```
GET    /api/v1/categories
GET    /api/v1/categories/{slug}
```

### Carrito (Autenticado)

```
GET    /api/v1/cart
POST   /api/v1/cart/items
PUT    /api/v1/cart/items/{id}
DELETE /api/v1/cart/items/{id}
DELETE /api/v1/cart/clear
```

### Órdenes (Autenticado)

```
GET    /api/v1/orders
GET    /api/v1/orders/{id}
POST   /api/v1/checkout
POST   /api/v1/orders/{id}/cancel
```

### Admin (Admin Role)

```
GET    /api/v1/admin/dashboard
CRUD   /api/v1/admin/categories
CRUD   /api/v1/admin/products
CRUD   /api/v1/admin/orders
GET    /api/v1/admin/customers
CRUD   /api/v1/admin/settings
GET    /api/v1/admin/reports/sales
GET    /api/v1/admin/reports/products
GET    /api/v1/admin/reports/customers
```

### Webhooks

```
POST   /api/webhooks/mercadopago
POST   /api/webhooks/andreani
```

## Testing

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=AuthTest

# Con cobertura
php artisan test --coverage
```

## Arquitectura

El proyecto sigue una arquitectura limpia con las siguientes capas:

```
app/
├── Contracts/          # Interfaces (Repository, Service)
├── Domain/            # Lógica de dominio (Enums, ValueObjects)
├── DTOs/              # Data Transfer Objects
├── Exceptions/        # Excepciones personalizadas
├── Http/              # Controllers, Requests, Resources
├── Jobs/              # Queue Jobs
├── Events/            # Eventos
├── Listeners/         # Event Listeners
├── Messages/          # Mensajes centralizados
├── Models/            # Eloquent Models
├── Repositories/      # Implementaciones de repositorios
├── Services/          # Servicios de negocio
└── Support/           # Helpers, Traits, Constants
```

## Seguridad

- Autenticación basada en tokens (Sanctum)
- Validación de inputs con FormRequests
- Rate limiting en endpoints críticos
- Protección CSRF
- Sanitización de datos
- Tokens de refresh para sesiones seguras

## Usuarios de Prueba

Después de ejecutar los seeders:

```
Admin:
- Email: admin@example.com
- Password: password

Customer:
- Email: customer@example.com
- Password: password
```

## Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## Licencia

Este proyecto está bajo licencia MIT.

## Soporte

Para reportar bugs o solicitar features, por favor abre un issue en el repositorio.

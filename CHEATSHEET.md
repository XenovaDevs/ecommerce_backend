# Development Cheatsheet

Comandos útiles para desarrollo diario.

## Artisan

### Servidor

```bash
php artisan serve                    # http://localhost:8000
php artisan serve --port=8080        # Puerto personalizado
```

### Base de Datos

```bash
# Migraciones
php artisan migrate                  # Ejecutar pendientes
php artisan migrate:fresh            # Drop + migrate
php artisan migrate:fresh --seed     # + seeders
php artisan migrate:rollback         # Revertir último batch
php artisan migrate:status           # Ver estado

# Seeders
php artisan db:seed                  # Todos
php artisan db:seed --class=UserSeeder

# Tinker (REPL)
php artisan tinker
>>> User::count()
>>> Order::factory()->create()
```

### Cache

```bash
php artisan cache:clear              # Limpiar cache
php artisan config:clear             # Limpiar config
php artisan route:clear              # Limpiar rutas
php artisan view:clear               # Limpiar vistas

php artisan config:cache             # Cachear config
php artisan route:cache              # Cachear rutas
php artisan view:cache               # Cachear vistas
php artisan optimize                 # Todo junto
```

### Queues

```bash
php artisan queue:work redis         # Worker
php artisan queue:work redis --queue=high,default
php artisan queue:listen             # Con auto-reload
php artisan queue:failed             # Ver fallos
php artisan queue:retry all          # Reintentar todos
php artisan queue:flush              # Limpiar failed
```

### Generadores

```bash
# Modelos
php artisan make:model Product -mfsc
# -m: migration
# -f: factory
# -s: seeder
# -c: controller

# Controllers
php artisan make:controller Api/V1/ProductController --api

# Requests
php artisan make:request CreateProductRequest

# Resources
php artisan make:resource ProductResource

# Jobs
php artisan make:job ProcessPayment

# Events
php artisan make:event OrderCreated

# Listeners
php artisan make:listener SendOrderEmail

# Migrations
php artisan make:migration create_products_table
php artisan make:migration add_status_to_orders_table

# Policies
php artisan make:policy OrderPolicy --model=Order

# Exceptions
php artisan make:exception OrderNotFoundException
```

## Testing

```bash
# Ejecutar todos
php artisan test

# Específicos
php artisan test --filter=LoginTest
php artisan test --filter=test_user_can_login

# Coverage
php artisan test --coverage
php artisan test --coverage-html coverage

# Paralelo
php artisan test --parallel

# Con output
php artisan test --testdox
```

## Composer

```bash
composer install                     # Instalar deps
composer update                      # Actualizar deps
composer require package/name        # Agregar dep
composer require --dev package/name  # Dev dep
composer dump-autoload               # Regenerar autoload
```

## Code Quality

```bash
# Laravel Pint (formato)
./vendor/bin/pint                    # Todo
./vendor/bin/pint app/Services       # Carpeta específica
./vendor/bin/pint --test             # Verificar sin cambiar

# PHPStan (análisis estático)
./vendor/bin/phpstan analyse         # Si está instalado
```

## Git

```bash
# Workflow común
git status
git add .
git commit -m "feat: add product filters"
git push origin feature/product-filters

# Branches
git checkout -b feature/nueva-feature
git checkout main
git branch -d feature/vieja-feature

# Stash
git stash                            # Guardar cambios
git stash pop                        # Aplicar cambios
git stash list                       # Ver stash
```

## Docker

```bash
# Iniciar
docker-compose up -d

# Detener
docker-compose down

# Ver logs
docker-compose logs -f app
docker-compose logs -f queue

# Ejecutar comandos
docker-compose exec app php artisan migrate
docker-compose exec app composer install

# Rebuild
docker-compose up -d --build
```

## Redis CLI

```bash
redis-cli                            # Conectar
> KEYS *                             # Ver todas las keys
> GET key_name                       # Ver valor
> FLUSHALL                           # Limpiar todo
> MONITOR                            # Ver comandos en tiempo real
```

## MySQL CLI

```bash
mysql -u root -p

# Comandos comunes
SHOW DATABASES;
USE ecommerce;
SHOW TABLES;
DESCRIBE users;
SELECT * FROM users LIMIT 10;

# Backup
mysqldump -u root -p ecommerce > backup.sql

# Restore
mysql -u root -p ecommerce < backup.sql
```

## Debugging

```bash
# Ver logs
tail -f storage/logs/laravel.log

# Limpiar logs
> storage/logs/laravel.log

# En código
\Log::info('Debug info', ['data' => $data]);
dd($variable);                       # Dump and die
dump($variable);                     # Dump
```

## API Testing (curl)

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Get con auth
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer TOKEN"

# Post con auth
curl -X POST http://localhost:8000/api/v1/cart/items \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"product_id":1,"quantity":2}'
```

## Performance

```bash
# Ver rutas lentas
php artisan route:list --sort=method

# Optimizar en producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

composer install --optimize-autoloader --no-dev

# Query logging (en código)
\DB::enableQueryLog();
// ... tu código
dd(\DB::getQueryLog());
```

## Mantenimiento

```bash
# Modo mantenimiento
php artisan down
php artisan down --secret="1630542a-246b-4b66-afa1-dd72a4c43515"
# Acceder: /1630542a-246b-4b66-afa1-dd72a4c43515

php artisan up                       # Volver online
```

## Enlaces Útiles

- Laravel Docs: https://laravel.com/docs
- Sanctum: https://laravel.com/docs/sanctum
- Eloquent: https://laravel.com/docs/eloquent
- Testing: https://laravel.com/docs/testing
- Pest: https://pestphp.com/docs

## Variables de Entorno (.env)

```env
# Desarrollo
APP_DEBUG=true
APP_ENV=local

# Producción
APP_DEBUG=false
APP_ENV=production
```

## Tips

1. **Usa Tinker para probar código rápido**
2. **Siempre escribe tests para nuevas features**
3. **Cachea en producción (config, routes, views)**
4. **Usa queue:work con supervisor en producción**
5. **Monitorea logs con herramientas como Sentry**
6. **Haz backups regulares de la base de datos**
7. **Usa migraciones para cambios en DB, nunca ALTER TABLE manual**
8. **Documenta APIs públicas**
9. **Usa Redis para cache y sessions en producción**
10. **Revisa código con Laravel Pint antes de commit**

# Contributing Guide

Gracias por considerar contribuir al proyecto Ecommerce Backend.

## Código de Conducta

- Sé respetuoso y profesional
- Acepta críticas constructivas
- Enfócate en lo mejor para el proyecto

## Cómo Contribuir

### Reportar Bugs

1. Verifica que el bug no haya sido reportado previamente
2. Crea un issue con:
   - Descripción clara del problema
   - Pasos para reproducir
   - Comportamiento esperado vs actual
   - Logs relevantes
   - Versión de PHP, Laravel, etc.

### Proponer Features

1. Crea un issue describiendo:
   - Problema que resuelve
   - Propuesta de solución
   - Alternativas consideradas
   - Impacto en el proyecto

### Pull Requests

1. **Fork el repositorio**
2. **Crea una rama desde `main`**:
   ```bash
   git checkout -b feature/mi-feature
   # o
   git checkout -b fix/mi-fix
   ```

3. **Sigue las convenciones de código**:
   - PHP 8.2+ features
   - PSR-12 coding standard
   - PHPDoc comments con @ai-context
   - Tipos estrictos (`declare(strict_types=1)`)
   - Named arguments donde sea apropiado

4. **Escribe tests**:
   ```bash
   php artisan test --filter=MiTest
   ```

5. **Verifica calidad del código**:
   ```bash
   ./vendor/bin/pint
   ```

6. **Commit con mensajes claros**:
   ```bash
   git commit -m "feat: agregar validación de stock en checkout"
   git commit -m "fix: corregir cálculo de impuestos"
   git commit -m "docs: actualizar README con ejemplos"
   ```

   Formato: `tipo: descripción`

   Tipos:
   - `feat`: Nueva feature
   - `fix`: Bug fix
   - `docs`: Documentación
   - `style`: Formato (no afecta lógica)
   - `refactor`: Refactorización
   - `test`: Tests
   - `chore`: Tareas de mantenimiento

7. **Push y crea PR**:
   ```bash
   git push origin feature/mi-feature
   ```

8. **Describe tu PR**:
   - ¿Qué cambia?
   - ¿Por qué es necesario?
   - ¿Cómo se probó?
   - Screenshots si aplica

## Estructura de Archivos

### Nuevo Módulo

Para agregar un nuevo módulo (ej: Reviews):

```
app/
├── Contracts/
│   ├── Repositories/
│   │   └── ReviewRepositoryInterface.php
│   └── Services/
│       └── ReviewServiceInterface.php
├── Domain/
│   └── Enums/
│       └── ReviewStatus.php
├── DTOs/
│   └── Review/
│       └── CreateReviewDTO.php
├── Exceptions/
│   └── Review/
│       └── ReviewNotFoundException.php
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── ReviewController.php
│   ├── Requests/Review/
│   │   └── CreateReviewRequest.php
│   └── Resources/
│       └── ReviewResource.php
├── Models/
│   └── Review.php
├── Repositories/
│   └── Eloquent/
│       └── ReviewRepository.php
└── Services/
    └── Review/
        └── ReviewService.php
```

### Migración

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
```

### Test

```php
<?php

namespace Tests\Feature\Review;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'comment' => 'Great product!',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);
    }
}
```

## Convenciones

### Naming

- **Classes**: PascalCase (`OrderService`, `ProductController`)
- **Methods**: camelCase (`createOrder()`, `calculateTotal()`)
- **Variables**: camelCase (`$orderTotal`, `$userId`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_ITEMS`, `DEFAULT_CURRENCY`)
- **Database tables**: snake_case plural (`orders`, `order_items`)
- **Database columns**: snake_case (`user_id`, `created_at`)

### PHPDoc

Todos los archivos deben tener comentarios @ai-context:

```php
<?php

namespace App\Services\Review;

/**
 * @ai-context ReviewService handles all review-related business logic.
 *             Validates reviews, checks purchase history, manages moderation.
 * @ai-dependencies
 *   - ReviewRepositoryInterface: Data access
 *   - OrderService: Verify purchase before review
 * @ai-flow
 *   1. Check user purchased product
 *   2. Validate rating (1-5)
 *   3. Create review with 'pending' status
 *   4. Dispatch moderation job
 */
class ReviewService
{
    // ...
}
```

### Exceptions

Todas las excepciones deben extender `BaseException`:

```php
<?php

namespace App\Exceptions\Review;

use App\Exceptions\BaseException;

class ReviewNotFoundException extends BaseException
{
    public function __construct(int $reviewId)
    {
        parent::__construct(
            "Review not found",
            ['review_id' => $reviewId]
        );
    }

    public function getErrorCode(): string
    {
        return 'REVIEW_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }

    public function isOperational(): bool
    {
        return true;
    }
}
```

### Mensajes

Centralizados en `app/Messages/`:

```php
// app/Messages/ErrorMessages.php
public const REVIEW = [
    'NOT_FOUND' => 'Review not found',
    'NOT_PURCHASED' => 'You must purchase this product before reviewing',
    'ALREADY_REVIEWED' => 'You have already reviewed this product',
];
```

## Testing

### Ejecutar Tests

```bash
# Todos los tests
php artisan test

# Tests específicos
php artisan test --filter=ReviewTest

# Con coverage
php artisan test --coverage

# Paralelo
php artisan test --parallel
```

### Cobertura Mínima

- Nuevas features: 80% cobertura
- Bug fixes: Test que reproduce el bug

## Code Review

Tu PR será revisado por:

1. **Arquitectura**: ¿Sigue clean architecture?
2. **SOLID**: ¿Cumple principios SOLID?
3. **Seguridad**: ¿Hay vulnerabilidades?
4. **Tests**: ¿Tiene tests adecuados?
5. **Documentación**: ¿Está documentado?
6. **Performance**: ¿Es eficiente?

## Preguntas

Si tienes dudas:
1. Revisa la documentación existente
2. Busca en issues cerrados
3. Crea un issue con tu pregunta

¡Gracias por contribuir! 🎉

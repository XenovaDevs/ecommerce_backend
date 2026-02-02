@echo off
echo.
echo ================================
echo Ecommerce Backend - Inicializacion
echo ================================
echo.

REM Instalar dependencias
echo Instalando dependencias...
call composer install

REM Verificar .env
if not exist .env (
    echo Creando archivo .env...
    copy .env.example .env
    php artisan key:generate
) else (
    echo Archivo .env ya existe
)

REM Publicar configuraciones
echo Publicando configuraciones...
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

REM Crear storage link
echo Creando symlink de storage...
php artisan storage:link

echo.
echo ================================
echo Siguientes pasos:
echo ================================
echo.
echo 1. Configura tu base de datos en .env
echo 2. Ejecuta: php artisan migrate
echo 3. Ejecuta: php artisan db:seed
echo 4. Inicia servidor: php artisan serve
echo 5. Inicia queue: php artisan queue:work redis
echo 6. Ejecuta tests: php artisan test
echo.
echo Inicializacion completa!
echo.
pause

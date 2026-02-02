#!/bin/bash

# Script de inicialización para desarrollo
# Ejecutar: bash init-dev.sh

echo "🚀 Inicializando proyecto Ecommerce Backend..."
echo ""

# Verificar composer
if ! command -v composer &> /dev/null; then
    echo "❌ Composer no está instalado. Por favor instala Composer primero."
    exit 1
fi

# Instalar dependencias
echo "📦 Instalando dependencias..."
composer install

# Verificar .env
if [ ! -f .env ]; then
    echo "📝 Creando archivo .env..."
    cp .env.example .env
    php artisan key:generate
else
    echo "✅ Archivo .env ya existe"
fi

# Verificar Redis
echo "🔍 Verificando Redis..."
if command -v redis-cli &> /dev/null; then
    if redis-cli ping > /dev/null 2>&1; then
        echo "✅ Redis está corriendo"
    else
        echo "⚠️  Redis no está corriendo. Inicia Redis con: redis-server"
    fi
else
    echo "⚠️  Redis no está instalado. Instala Redis para usar cache y queues."
fi

# Verificar MySQL
echo "🔍 Verificando MySQL..."
if command -v mysql &> /dev/null; then
    echo "✅ MySQL está instalado"
else
    echo "⚠️  MySQL no está instalado. Instala MySQL para usar la base de datos."
fi

# Publicar configuraciones
echo "📋 Publicando configuraciones..."
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Crear storage link
echo "🔗 Creando symlink de storage..."
php artisan storage:link

echo ""
echo "✨ Siguiente pasos:"
echo ""
echo "1. Configura tu base de datos en .env:"
echo "   DB_DATABASE=ecommerce"
echo "   DB_USERNAME=root"
echo "   DB_PASSWORD="
echo ""
echo "2. Ejecuta las migraciones:"
echo "   php artisan migrate"
echo ""
echo "3. Ejecuta los seeders (opcional):"
echo "   php artisan db:seed"
echo ""
echo "4. Inicia el servidor de desarrollo:"
echo "   php artisan serve"
echo ""
echo "5. Inicia el queue worker (en otra terminal):"
echo "   php artisan queue:work redis"
echo ""
echo "6. Ejecuta los tests:"
echo "   php artisan test"
echo ""
echo "✅ Inicialización completa!"

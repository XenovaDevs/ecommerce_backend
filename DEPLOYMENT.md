# Deployment Guide

Guía de despliegue para el backend de ecommerce en producción.

## Requisitos del Servidor

### Mínimos

- PHP 8.2+
- MySQL 8.0+ o MariaDB 10.3+
- Redis 6.0+
- Composer 2.x
- Nginx o Apache
- Supervisor (para queue workers)

### Recomendados

- 2 CPU cores
- 4 GB RAM
- 20 GB SSD
- HTTPS/SSL certificate

## Pasos de Despliegue

### 1. Preparar el Servidor

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP y extensiones
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd -y

# Instalar MySQL
sudo apt install mysql-server -y

# Instalar Redis
sudo apt install redis-server -y

# Instalar Nginx
sudo apt install nginx -y

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Supervisor
sudo apt install supervisor -y
```

### 2. Clonar y Configurar Aplicación

```bash
# Clonar repositorio
cd /var/www
sudo git clone <repository-url> ecommerce-backend
cd ecommerce-backend

# Permisos
sudo chown -R www-data:www-data /var/www/ecommerce-backend
sudo chmod -R 755 /var/www/ecommerce-backend
sudo chmod -R 775 /var/www/ecommerce-backend/storage
sudo chmod -R 775 /var/www/ecommerce-backend/bootstrap/cache

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Configurar ambiente
cp .env.example .env
nano .env
```

### 3. Configurar .env para Producción

```env
APP_NAME="Ecommerce API"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

FRONTEND_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_prod
DB_USERNAME=ecommerce_user
DB_PASSWORD=strong_password_here

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Sanctum
SANCTUM_STATEFUL_DOMAINS=yourdomain.com

# API
API_RATE_LIMIT_DEFAULT=60
API_RATE_LIMIT_AUTH=5
API_CACHE_ENABLED=true
API_REQUIRE_HTTPS=true

# Mercado Pago (Production)
MERCADOPAGO_PUBLIC_KEY=your_production_public_key
MERCADOPAGO_ACCESS_TOKEN=your_production_access_token
MERCADOPAGO_WEBHOOK_SECRET=your_webhook_secret

# Andreani (Production)
ANDREANI_USERNAME=your_production_username
ANDREANI_PASSWORD=your_production_password
```

### 4. Configurar Base de Datos

```bash
# Crear base de datos
mysql -u root -p
```

```sql
CREATE DATABASE ecommerce_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecommerce_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON ecommerce_prod.* TO 'ecommerce_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Generar key
php artisan key:generate

# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders (solo settings)
php artisan db:seed --class=SettingSeeder --force

# Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 5. Configurar Nginx

```bash
sudo nano /etc/nginx/sites-available/ecommerce-api
```

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/ecommerce-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Activar sitio
sudo ln -s /etc/nginx/sites-available/ecommerce-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 6. Configurar SSL con Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d api.yourdomain.com
```

### 7. Configurar Queue Workers con Supervisor

```bash
sudo nano /etc/supervisor/conf.d/ecommerce-worker.conf
```

```ini
[program:ecommerce-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ecommerce-backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=high,payments,orders,default,notifications,shipping,stock,webhooks,reports,low
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/ecommerce-backend/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ecommerce-worker:*
```

### 8. Configurar Laravel Reverb (WebSockets)

```bash
sudo nano /etc/supervisor/conf.d/ecommerce-reverb.conf
```

```ini
[program:ecommerce-reverb]
command=php /var/www/ecommerce-backend/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/ecommerce-backend/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ecommerce-reverb
```

### 9. Configurar Cron Jobs

```bash
sudo crontab -e -u www-data
```

```cron
* * * * * cd /var/www/ecommerce-backend && php artisan schedule:run >> /dev/null 2>&1
```

### 10. Monitoreo y Logs

```bash
# Ver logs en tiempo real
tail -f /var/www/ecommerce-backend/storage/logs/laravel.log

# Ver status de workers
sudo supervisorctl status

# Reiniciar workers
sudo supervisorctl restart ecommerce-worker:*
```

## Actualizaciones

```bash
cd /var/www/ecommerce-backend

# Activar modo mantenimiento
php artisan down

# Pull cambios
git pull origin main

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Ejecutar migraciones
php artisan migrate --force

# Limpiar y cachear
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar workers
sudo supervisorctl restart ecommerce-worker:*

# Desactivar modo mantenimiento
php artisan up
```

## Backup

### Base de Datos

```bash
# Backup manual
mysqldump -u ecommerce_user -p ecommerce_prod > backup-$(date +%Y%m%d).sql

# Automatizar backups diarios
sudo crontab -e
```

```cron
0 2 * * * mysqldump -u ecommerce_user -p'password' ecommerce_prod > /backups/db-$(date +\%Y\%m\%d).sql
```

### Archivos

```bash
# Backup storage
tar -czf storage-backup-$(date +%Y%m%d).tar.gz storage/
```

## Seguridad

### Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Fail2ban

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
```

### Redis

```bash
sudo nano /etc/redis/redis.conf
```

```conf
bind 127.0.0.1
requirepass your_redis_password
```

## Troubleshooting

### Permisos

```bash
sudo chown -R www-data:www-data /var/www/ecommerce-backend
sudo chmod -R 755 /var/www/ecommerce-backend
sudo chmod -R 775 /var/www/ecommerce-backend/storage
sudo chmod -R 775 /var/www/ecommerce-backend/bootstrap/cache
```

### Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log

# PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

### Performance

```bash
# Optimizar MySQL
sudo mysql_secure_installation

# Aumentar límites PHP
sudo nano /etc/php/8.2/fpm/php.ini
# memory_limit = 512M
# max_execution_time = 300
# upload_max_filesize = 50M
# post_max_size = 50M

sudo systemctl restart php8.2-fpm
```

## Monitoreo con Herramientas Externas

- **New Relic** - Application Performance Monitoring
- **Sentry** - Error tracking
- **DataDog** - Infrastructure monitoring
- **Uptime Robot** - Uptime monitoring

## Rollback

```bash
# Revertir código
git reset --hard <commit-hash>

# Revertir migraciones
php artisan migrate:rollback --step=1

# Limpiar cache
php artisan cache:clear
php artisan config:clear

# Reiniciar workers
sudo supervisorctl restart ecommerce-worker:*
```

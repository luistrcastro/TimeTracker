#!/bin/sh
set -e
php artisan config:cache || echo "[start] config:cache failed, continuing without cache"
chown -R www-data:www-data /app/storage /app/bootstrap/cache
php-fpm -D
exec nginx -g "daemon off;"

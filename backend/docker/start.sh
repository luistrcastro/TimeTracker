#!/bin/sh
set -e
php artisan config:cache || echo "[start] config:cache failed, continuing without cache"
php-fpm -D
exec nginx -g "daemon off;"

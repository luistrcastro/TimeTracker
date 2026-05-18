#!/bin/sh
set -e
php artisan config:cache
php-fpm -D
exec nginx -g "daemon off;"

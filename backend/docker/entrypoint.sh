#!/bin/sh
set -e

LOCK_HASH_FILE="/app/vendor/.composer.lock.md5"
CURRENT_HASH=$(md5sum /app/composer.lock | awk '{print $1}')

if [ ! -d "/app/vendor" ] || [ ! -f "$LOCK_HASH_FILE" ] || [ "$CURRENT_HASH" != "$(cat $LOCK_HASH_FILE)" ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --no-progress
    echo "$CURRENT_HASH" > "$LOCK_HASH_FILE"
else
    echo "[entrypoint] Composer dependencies up to date, skipping install."
fi

if [ ! -d "/app/node_modules/puppeteer" ]; then
    echo "[entrypoint] Installing puppeteer..."
    npm install puppeteer --prefix /app --silent
fi

exec "$@"

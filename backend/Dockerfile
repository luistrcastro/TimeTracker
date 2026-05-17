# syntax=docker/dockerfile:1

# ── Stage 1: composer deps ────────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ── Stage 2: dev (hot-reload, no optimize) ───────────────────────────────────
FROM php:8.3-fpm-alpine AS dev
RUN apk add --no-cache \
        linux-headers \
        postgresql-dev \
        chromium \
        nss \
        freetype \
        harfbuzz \
        ca-certificates \
        ttf-freefont \
        font-noto \
        nodejs \
        npm \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk add --no-cache curl
ENV BROWSERSHOT_CHROMIUM_PATH=/usr/bin/chromium-browser
ENV BROWSERSHOT_NODE_PATH=/usr/bin/node
ENV BROWSERSHOT_NPM_PATH=/usr/bin/npm
WORKDIR /app
RUN npm install puppeteer --prefix /app
# In dev, the source is bind-mounted at runtime; copy nothing from vendor stage.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
EXPOSE 8020

# ── Stage 3: runtime (prod, includes chromium for Browsershot) ───────────────
FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache \
        linux-headers \
        postgresql-dev \
        chromium \
        nss \
        freetype \
        harfbuzz \
        ca-certificates \
        ttf-freefont \
        font-noto \
        nodejs \
        npm \
    && docker-php-ext-install pdo pdo_pgsql opcache
ENV BROWSERSHOT_CHROMIUM_PATH=/usr/bin/chromium-browser
ENV BROWSERSHOT_NODE_PATH=/usr/bin/node
ENV BROWSERSHOT_NPM_PATH=/usr/bin/npm
WORKDIR /app
RUN npm install puppeteer --prefix /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN rm -f bootstrap/cache/*.php && php artisan optimize && php artisan event:cache
EXPOSE 9000

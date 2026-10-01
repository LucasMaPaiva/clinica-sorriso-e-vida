# syntax=docker/dockerfile:1

# ============================================================
# Base: PHP-FPM + extensões exigidas por Laravel/Filament/Postgres
# ============================================================
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        sqlite-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        linux-headers \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # opcache não entra na lista: já vem embutido na imagem do PHP 8.5
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        intl \
        zip \
        gd \
        bcmath \
        pcntl \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# ============================================================
# Dev: composer disponível, código montado via bind mount
# ============================================================
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Alinha o UID do www-data com o usuário do host (bind mounts sem dor)
ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && usermod -u ${UID} www-data \
    && groupmod -g ${GID} www-data

USER www-data

# ============================================================
# Assets: compila CSS/JS (Vite) para a imagem de produção
# ============================================================
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# ============================================================
# Prod: código copiado, dependências otimizadas, sem composer/node
# ============================================================
FROM base AS prod

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY .docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

RUN composer dump-autoload --optimize --no-dev \
    && rm /usr/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# ============================================================
# Nginx de produção: recebe os mesmos arquivos públicos gerados
# na imagem PHP. Assim o servidor não depende de bind mounts.
# ============================================================
FROM nginx:1.29-alpine AS nginx-prod

COPY .docker/nginx/conf.d /etc/nginx/conf.d
COPY --from=prod /var/www/html/public /var/www/html/public

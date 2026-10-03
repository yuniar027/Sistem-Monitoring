# syntax=docker/dockerfile:1

# Frontend Build

FROM node:24-bookworm-slim AS frontend 

WORKDIR /app

COPY package.json ./

RUN npm install 

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build 

# PHP / Laravel Application

FROM dunglas/frankenphp:php8.3-bookworm AS app

WORKDIR /app


RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        mbstring \
        xml \
        curl \
        zip \
        bcmath \
        gd \
        intl \
        pcntl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP depedencies first for Docker layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts 

# Copy application
COPY . . 

# Generate optimized autoload and execute laravel composer scripts
RUN composer dump-autoload --optimize

# Copy Vite production assets
COPY --from=frontend /app/public/build ./public/build


# Laravel writable direcories
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R ug+rwx \
        storage \
        bootstrap/cache

# FrankenPHP/Caddy Configuration
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]


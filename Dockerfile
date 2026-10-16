# SenhaBaixa VIP — Laravel na VPS (Apache + PHP 8.2)
# Build em 2 estágios: Node compila o Vite, PHP serve o app.

# ── Estágio 1: assets Vite ────────────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json tsconfig.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
RUN npm run build

# ── Estágio 2: PHP + Apache ───────────────────────────────────
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl git unzip \
    libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath intl zip gd opcache pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache: DocumentRoot no /public do Laravel + .htaccess liberado
RUN a2enmod rewrite \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload -o \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=60s \
    CMD curl -f http://127.0.0.1/up || exit 1

EXPOSE 80

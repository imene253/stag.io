# Stage 1 - Build Frontend (Vite)
FROM node:18 AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.2-fpm AS backend

# Prevent interactive prompts during builds
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies and build tools required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    git curl unzip zlib1g-dev libzip-dev libpq-dev libonig-dev libxml2-dev pkg-config zip \
    && docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

# Copy built frontend from Stage 1 (if present)
COPY --from=frontend /app/public/dist ./public/dist

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Laravel setup
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

CMD ["php-fpm"]

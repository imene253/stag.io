# Stage 1 - Build Frontend (Vite)
FROM node:20 AS frontend
WORKDIR /app
# copy only package files first for caching
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.4-fpm AS backend

# Prevent interactive prompts during builds
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies and build tools required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    git curl unzip zlib1g-dev libzip-dev libpq-dev libonig-dev libxml2-dev pkg-config zip \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

# Copy built frontend from Stage 1 (if present)
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Laravel setup
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

# Use artisan serve so the container listens on a port for Render
CMD ["sh","-lc","php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]

# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.4-fpm AS backend

# Prevent interactive prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential git curl unzip zlib1g-dev libzip-dev libpq-dev libonig-dev libxml2-dev pkg-config zip \
    libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql pdo_sqlite mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app files
COPY . .

# Ensure storage directories and SQLite database exist with correct permissions
RUN mkdir -p database storage/framework/sessions storage/framework/cache storage/framework/views \
    && touch database/database.sqlite \
    && chown -R www-data:www-data database storage bootstrap/cache \
    && chmod -R 775 database storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Ensure .env exists and generate APP_KEY
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && php artisan key:generate --force

# Generate OpenAPI spec if swagger-php exists
RUN if [ -f vendor/bin/openapi ]; then \
        vendor/bin/openapi --output public/openapi.generated.yaml ./app ./routes || true; \
        if [ -f public/openapi.generated.yaml ] && [ $(stat -c%s public/openapi.generated.yaml) -gt 400 ]; then \
            mv public/openapi.generated.yaml public/openapi.yaml; \
        else \
            echo "Generated OpenAPI spec missing or too small — keeping existing public/openapi.yaml"; \
            rm -f public/openapi.generated.yaml || true; \
        fi; \
    fi

# Clear Laravel caches
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

# Create startup script that respects Render environment variables.
RUN echo '#!/bin/sh\n\
set -e\n\
echo "=== Starting app with DB_CONNECTION=${DB_CONNECTION:-sqlite} ==="\n\
mkdir -p /var/www/storage/framework/sessions /var/www/storage/framework/cache /var/www/storage/framework/views /var/www/bootstrap/cache\n\
chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true\n\
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then\n\
  mkdir -p /var/www/database\n\
  touch /var/www/database/database.sqlite\n\
  chmod 664 /var/www/database/database.sqlite || true\n\
fi\n\
php artisan config:clear || true\n\
php artisan route:clear || true\n\
php artisan view:clear || true\n\
echo "=== Running migrations ==="\n\
php artisan migrate --force\n\
echo "=== Seeding admin user ==="\n\
php artisan db:seed --class=AdminSeeder --force || true\n\
echo "=== Starting Laravel server ==="\n\
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}' > /var/www/start.sh && \
    chmod +x /var/www/start.sh

# Use startup script to run migrations/seeders on container startup
CMD ["/var/www/start.sh"]
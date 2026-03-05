# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.4-fpm AS backend

# Prevent interactive prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential git curl unzip zlib1g-dev libzip-dev libpq-dev libonig-dev libxml2-dev pkg-config zip \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app files
COPY . .

# Ensure storage directories exist with correct permissions
RUN mkdir -p storage/framework/sessions storage/framework/cache storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

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

# Create startup script that runs migrations and seeders before starting server
RUN echo '#!/bin/sh\n\
set -e\n\
echo "=== Ensuring DB_CONNECTION is set to pgsql ==="\n\
if [ -z "$DB_CONNECTION" ]; then\n\
    export DB_CONNECTION=pgsql\n\
    echo "DB_CONNECTION not set, defaulting to pgsql"\n\
fi\n\
if [ -f /var/www/.env ]; then\n\
    if ! grep -q "^DB_CONNECTION=" /var/www/.env; then\n\
        echo "DB_CONNECTION=$DB_CONNECTION" >> /var/www/.env\n\
    else\n\
        sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=$DB_CONNECTION/" /var/www/.env\n\
    fi\n\
fi\n\
echo "=== Ensuring directories exist ==="\n\
mkdir -p /var/www/storage/framework/sessions /var/www/storage/framework/cache /var/www/storage/framework/views\n\
chmod -R 775 /var/www/storage /var/www/bootstrap/cache\n\
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache\n\
echo "=== Removing cached config file ==="\n\
rm -f /var/www/bootstrap/cache/config.php\n\
echo "=== Clearing all caches ==="\n\
php artisan config:clear 2>/dev/null || true\n\
php artisan cache:clear 2>/dev/null || true\n\
php artisan route:clear 2>/dev/null || true\n\
php artisan view:clear 2>/dev/null || true\n\
echo "=== Running database migrations ==="\n\
php artisan migrate --force\n\
if [ $? -ne 0 ]; then\n\
    echo "ERROR: Migrations failed!"\n\
    exit 1\n\
fi\n\
echo "=== Seeding admin user ==="\n\
php artisan db:seed --class=AdminSeeder --force || echo "Note: AdminSeeder may have already run (admin exists)"\n\
echo "=== Starting Laravel server ==="\n\
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}' > /var/www/start.sh && \
    chmod +x /var/www/start.sh

# Use startup script to run migrations/seeders on container startup
CMD ["/var/www/start.sh"]
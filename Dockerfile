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
echo "=== Checking environment variables ==="\n\
echo "DB_CONNECTION=${DB_CONNECTION:-not set}"\n\
echo "DB_HOST=${DB_HOST:-not set}"\n\
echo "DB_DATABASE=${DB_DATABASE:-not set}"\n\
echo "DB_USERNAME=${DB_USERNAME:-not set}"\n\
echo "SESSION_DRIVER=${SESSION_DRIVER:-not set}"\n\
echo "=== Removing .env file to force use of environment variables ==="\n\
if [ -f /var/www/.env ]; then\n\
    echo "Backing up .env and removing DB_* and SESSION_DRIVER entries"\n\
    grep -v "^DB_" /var/www/.env | grep -v "^SESSION_DRIVER=" > /var/www/.env.tmp || true\n\
    mv /var/www/.env.tmp /var/www/.env || true\n\
fi\n\
echo "=== Ensuring directories exist ==="\n\
mkdir -p /var/www/storage/framework/sessions /var/www/storage/framework/cache /var/www/storage/framework/views\n\
chmod -R 775 /var/www/storage /var/www/bootstrap/cache\n\
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache\n\
echo "=== Removing ALL cached files aggressively ==="\n\
rm -rf /var/www/bootstrap/cache/*.php\n\
rm -rf /var/www/storage/framework/cache/data/*\n\
rm -rf /var/www/storage/framework/views/*\n\
echo "=== Clearing all caches with environment variables ==="\n\
DB_CONNECTION=${DB_CONNECTION:-pgsql} php artisan config:clear 2>&1 || true\n\
DB_CONNECTION=${DB_CONNECTION:-pgsql} php artisan cache:clear 2>&1 || true\n\
DB_CONNECTION=${DB_CONNECTION:-pgsql} php artisan route:clear 2>&1 || true\n\
DB_CONNECTION=${DB_CONNECTION:-pgsql} php artisan view:clear 2>&1 || true\n\
DB_CONNECTION=${DB_CONNECTION:-pgsql} php artisan optimize:clear 2>&1 || true\n\
echo "=== Verifying database connection ==="\n\
php artisan db:show 2>&1 || echo "Database connection check failed, but continuing..."\n\
echo "=== Running database migrations ==="\n\
php artisan migrate --force\n\
if [ $? -ne 0 ]; then\n\
    echo "ERROR: Migrations failed!"\n\
    echo "Check your DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD environment variables"\n\
    exit 1\n\
fi\n\
echo "=== Seeding admin user ==="\n\
php artisan db:seed --class=AdminSeeder --force || echo "Note: AdminSeeder may have already run (admin exists)"\n\
echo "=== Starting Laravel server ==="\n\
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}' > /var/www/start.sh && \
    chmod +x /var/www/start.sh

# Use startup script to run migrations/seeders on container startup
CMD ["/var/www/start.sh"]
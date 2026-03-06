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

# Create startup script that runs migrations and seeders before starting server
RUN echo '#!/bin/sh\n\
set -e\n\
echo "=== FORCING SQLite configuration ==="\n\
export DB_CONNECTION=sqlite\n\
unset DB_HOST\n\
unset DB_PORT\n\
unset DB_DATABASE\n\
unset DB_USERNAME\n\
unset DB_PASSWORD\n\
echo "DB_CONNECTION is now: sqlite"\n\
echo "=== Updating .env file to use SQLite ==="\n\
if [ -f /var/www/.env ]; then\n\
    grep -v "^DB_" /var/www/.env | grep -v "^SESSION_DRIVER=" > /var/www/.env.tmp 2>/dev/null || cat /var/www/.env > /var/www/.env.tmp\n\
    echo "DB_CONNECTION=sqlite" >> /var/www/.env.tmp\n\
    echo "SESSION_DRIVER=file" >> /var/www/.env.tmp\n\
    mv /var/www/.env.tmp /var/www/.env\n\
else\n\
    echo "DB_CONNECTION=sqlite" > /var/www/.env\n\
    echo "SESSION_DRIVER=file" >> /var/www/.env\n\
fi\n\
echo "=== Ensuring SQLite database file exists ==="\n\
mkdir -p /var/www/database\n\
touch /var/www/database/database.sqlite\n\
chmod 664 /var/www/database/database.sqlite\n\
chown www-data:www-data /var/www/database/database.sqlite\n\
echo "=== Ensuring directories exist ==="\n\
mkdir -p /var/www/storage/framework/sessions /var/www/storage/framework/cache /var/www/storage/framework/views\n\
chmod -R 775 /var/www/database /var/www/storage /var/www/bootstrap/cache\n\
chown -R www-data:www-data /var/www/database /var/www/storage /var/www/bootstrap/cache\n\
echo "=== Removing ALL cached files aggressively ==="\n\
rm -rf /var/www/bootstrap/cache/*\n\
rm -rf /var/www/storage/framework/cache/*\n\
rm -rf /var/www/storage/framework/views/*\n\
echo "=== Clearing all caches with SQLite ==="\n\
DB_CONNECTION=sqlite php artisan config:clear 2>&1 || echo "config:clear failed (continuing)"\n\
DB_CONNECTION=sqlite php artisan cache:clear 2>&1 || echo "cache:clear failed (continuing)"\n\
DB_CONNECTION=sqlite php artisan route:clear 2>&1 || echo "route:clear failed (continuing)"\n\
DB_CONNECTION=sqlite php artisan view:clear 2>&1 || echo "view:clear failed (continuing)"\n\
DB_CONNECTION=sqlite php artisan optimize:clear 2>&1 || echo "optimize:clear failed (continuing)"\n\
echo "=== Running database migrations with SQLite ==="\n\
DB_CONNECTION=sqlite php artisan migrate --force\n\
if [ $? -ne 0 ]; then\n\
    echo "ERROR: Migrations failed!"\n\
    exit 1\n\
fi\n\
echo "=== Seeding admin user ==="\n\
DB_CONNECTION=sqlite php artisan db:seed --class=AdminSeeder --force || echo "Note: AdminSeeder may have already run (admin exists)"\n\
echo "=== Starting Laravel server ==="\n\
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}' > /var/www/start.sh && \
    chmod +x /var/www/start.sh

# Use startup script to run migrations/seeders on container startup
CMD ["/var/www/start.sh"]
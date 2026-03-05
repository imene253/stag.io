# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.4-fpm AS backend

# Prevent interactive prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential git curl unzip zlib1g-dev libzip-dev libpq-dev libonig-dev libxml2-dev pkg-config zip \
    libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_sqlite mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app files
COPY . .

# Ensure SQLite database exists and permissions are correct
RUN mkdir -p database && touch database/database.sqlite \
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
echo "=== Setting session driver to file ==="\n\
export SESSION_DRIVER=file\n\
if [ -f /var/www/.env ]; then\n\
    sed -i "s/^SESSION_DRIVER=.*/SESSION_DRIVER=file/" /var/www/.env || echo "SESSION_DRIVER=file" >> /var/www/.env\n\
fi\n\
echo "=== Ensuring directories exist ==="\n\
mkdir -p /var/www/database /var/www/storage/framework/sessions\n\
touch /var/www/database/database.sqlite\n\
chmod 664 /var/www/database/database.sqlite\n\
chmod -R 775 /var/www/storage /var/www/bootstrap/cache\n\
chown -R www-data:www-data /var/www/database /var/www/storage /var/www/bootstrap/cache\n\
echo "=== Clearing all caches ==="\n\
php artisan config:clear\n\
php artisan cache:clear\n\
php artisan route:clear\n\
php artisan view:clear\n\
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
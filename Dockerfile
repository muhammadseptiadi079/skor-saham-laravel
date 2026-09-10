FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip git libzip-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN cp .env.example .env \
    && composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan key:generate --force \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache

# Render (and most PaaS) inject $PORT at runtime; php artisan serve must bind to it.
EXPOSE 10000
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}

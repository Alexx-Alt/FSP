FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.3-cli-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev nodejs npm $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl mbstring zip bcmath pcntl \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Wayfinder's Vite plugin uses the Laravel application while assets are generated,
# so the frontend build intentionally runs in the PHP image instead of an isolated Node stage.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && chown -R www-data:www-data storage bootstrap/cache public/build

USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

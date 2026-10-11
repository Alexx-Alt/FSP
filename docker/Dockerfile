# syntax=docker/dockerfile:1.7

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./

# Кеш и повторы защищают сборку от временных обрывов SSL.
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
ENV COMPOSER_PROCESS_TIMEOUT=1200
ENV COMPOSER_MAX_PARALLEL_HTTP=4

RUN --mount=type=cache,target=/tmp/composer-cache \
    set -eu; \
    attempt=1; \
    until composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts; do \
        if [ "$attempt" -ge 4 ]; then \
            echo "Composer не смог скачать зависимости после 4 попыток."; \
            exit 1; \
        fi; \
        echo "Временный сетевой сбой Composer. Повтор $attempt/4 через 10 секунд..."; \
        attempt=$((attempt + 1)); \
        sleep 10; \
    done

FROM php:8.4-cli-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev nodejs npm $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl mbstring zip bcmath pcntl \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Wayfinder использует Laravel во время сборки интерфейса.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && chown -R www-data:www-data storage bootstrap/cache public/build

USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

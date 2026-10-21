# Imagen de desarrollo para los microservicios Laravel de ColcaTrufis.
FROM composer:2.8.12 AS composer

FROM php:8.3.13-cli-alpine3.20
RUN apk add --no-cache postgresql-dev mysql-client postgresql-client \
    && docker-php-ext-install pdo_mysql pdo_pgsql pcntl
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh

ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

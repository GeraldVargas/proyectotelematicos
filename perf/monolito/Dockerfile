# Monolito Laravel para medir la línea base. Mismo PHP y servidor que los microservicios,
# para que la diferencia medida venga de la arquitectura y no del entorno.
FROM composer:2.8.12 AS composer

FROM php:8.3.13-cli-alpine3.20
RUN apk add --no-cache mysql-client && docker-php-ext-install pdo_mysql
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist
COPY . .
# En backend/app/Models varios archivos difieren en mayúsculas de su clase (TrufiRuta.php / class Trufiruta).
# Windows y macOS lo toleran; Linux no. Se enlazan aquí para no cambiar el monolito en producción.
RUN cd app/Models && for f in *.php; do \
      c=$(sed -n 's/^class \([A-Za-z0-9_]*\).*/\1/p' "$f"); \
      if [ -n "$c" ] && [ "$c.php" != "$f" ]; then ln -s "$f" "$c.php"; fi; \
    done
RUN touch .env && composer dump-autoload --optimize && php artisan package:discover --ansi

ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
CMD ["sh", "-c", "until php artisan migrate --force; do sleep 3; done; php /perf/sembrar.php && exec php artisan serve --no-reload --host=0.0.0.0 --port=8000"]

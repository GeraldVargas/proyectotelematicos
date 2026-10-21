#!/bin/sh
set -e

# Las claves viven en un volumen compartido con el Gateway (lee jwk.json)
php artisan jwt:claves

until php artisan migrate --force --seed; do
  echo "Esperando a la base de datos de usuarios..."
  sleep 3
done

exec "$@"

#!/bin/sh
set -e

# Solo el contenedor de la API corre migraciones; el worker espera a que existan las tablas
if [ "$RUN_MIGRATIONS" != "false" ]; then
  until php artisan migrate --force; do
    echo "Esperando a la base de datos de notificaciones..."
    sleep 3
  done
else
  until php artisan migrate:status >/dev/null 2>&1; do
    echo "Esperando a que la API cree las tablas..."
    sleep 3
  done
fi

exec "$@"

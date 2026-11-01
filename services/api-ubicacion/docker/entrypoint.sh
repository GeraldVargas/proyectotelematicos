#!/bin/sh
set -e

# El seeder agrega las rutas ausentes y conserva las ediciones existentes.
if [ "$RUN_MIGRATIONS" != "false" ]; then
until php artisan migrate --force --seed; do
  echo "Esperando a PostGIS..."
  sleep 3
done
fi

exec "$@"

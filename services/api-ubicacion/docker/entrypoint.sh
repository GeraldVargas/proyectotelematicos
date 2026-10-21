#!/bin/sh
set -e

# El seeder es idempotente: recarga las rutas de OpenStreetMap en cada arranque
until php artisan migrate --force --seed; do
  echo "Esperando a PostGIS..."
  sleep 3
done

exec "$@"

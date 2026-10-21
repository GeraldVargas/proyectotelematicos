#!/bin/sh
# Ejecuta los escenarios de carga con k6 en Docker (no hace falta instalar k6).
#   ./perf/run.sh monolito        # línea base, monolito en http://localhost:8000
#   ./perf/run.sh microservicios  # a través del API Gateway en http://localhost:8080
# Variables opcionales: BASE_URL, VUS (20), DURACION (2m), EMAIL, PASSWORD, SIN_RADIOTAXIS
set -e
TARGET=${1:-monolito}
case "$TARGET" in
  monolito) DEFAULT_URL=http://host.docker.internal:8000 ;;
  microservicios) DEFAULT_URL=http://host.docker.internal:8080 ;;
  *) echo "Uso: $0 monolito|microservicios" >&2; exit 1 ;;
esac
DIR=$(cd "$(dirname "$0")" && pwd)
docker run --rm -i \
  --add-host=host.docker.internal:host-gateway \
  -v "$DIR/k6:/scripts:ro" -v "$DIR/resultados:/resultados" \
  -e TARGET="$TARGET" -e BASE_URL="${BASE_URL:-$DEFAULT_URL}" \
  -e VUS="${VUS:-20}" -e DURACION="${DURACION:-2m}" \
  -e EMAIL="${EMAIL:-admin@colcatrufis.local}" -e PASSWORD="${PASSWORD:-admin12345}" \
  -e SIN_RADIOTAXIS="${SIN_RADIOTAXIS:-}" \
  grafana/k6:0.54.0 run /scripts/escenarios.js

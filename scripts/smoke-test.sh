#!/bin/sh
# Pruebas de humo extremo a extremo contra el API Gateway (Definition of Done del Sprint 1).
# Requiere: docker compose up -d   ·   uso: ./scripts/smoke-test.sh [http://localhost:8080]
set -u
GW=${1:-http://localhost:8080}
FALLOS=0

ok()   { printf '  \033[32m✓\033[0m %s\n' "$1"; }
mal()  { printf '  \033[31m✗\033[0m %s\n' "$1"; FALLOS=$((FALLOS + 1)); }
codigo() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
espera() { [ "$2" = "$3" ] && ok "$1 → $3" || mal "$1 → esperado $2, obtenido $3"; }
json() { python3 -c "import json,sys; d=json.load(sys.stdin); print(eval(sys.argv[1]))" "$1"; }

echo "Gateway: $GW"

echo "1. Rutas protegidas sin token"
espera "GET /api/ubicacion/rutas sin token" 401 "$(codigo "$GW/api/ubicacion/rutas")"
espera "GET /api/usuarios/perfil sin token" 401 "$(codigo "$GW/api/usuarios/perfil")"
espera "GET /api/notificaciones sin token" 401 "$(codigo "$GW/api/notificaciones")"
espera "GET /api/ubicacion/rutas con token falso" 401 "$(codigo -H 'Authorization: Bearer x.y.z' "$GW/api/ubicacion/rutas")"

echo "2. Login (ruta pública)"
espera "POST /api/usuarios/login con clave incorrecta" 401 \
  "$(codigo -X POST -H 'Content-Type: application/json' -d '{"email":"demo@colcatrufis.local","password":"mala"}' "$GW/api/usuarios/login")"
LOGIN=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"email":"demo@colcatrufis.local","password":"demo12345"}' "$GW/api/usuarios/login")
TOKEN=$(echo "$LOGIN" | json 'd["access_token"]' 2>/dev/null)
[ -n "$TOKEN" ] && ok "login devuelve access_token" || { mal "login no devolvió token: $LOGIN"; exit 1; }
AUTH="Authorization: Bearer $TOKEN"

echo "3. Con token válido llega al servicio correcto"
PERFIL=$(curl -s -H "$AUTH" "$GW/api/usuarios/perfil")
[ "$(echo "$PERFIL" | json 'd["email"]')" = "demo@colcatrufis.local" ] && ok "usuarios: /perfil" || mal "usuarios: $PERFIL"

RUTAS=$(curl -s -H "$AUTH" "$GW/api/ubicacion/rutas")
N=$(echo "$RUTAS" | json 'len(d["features"]) if d["type"]=="FeatureCollection" else 0')
[ "${N:-0}" -ge 3 ] && ok "ubicacion: FeatureCollection con $N rutas" || mal "ubicacion: $RUTAS"
ID=$(echo "$RUTAS" | json 'd["features"][0]["id"]')
espera "GET /api/ubicacion/rutas/$ID" 200 "$(codigo -H "$AUTH" "$GW/api/ubicacion/rutas/$ID")"
espera "GET /api/ubicacion/paradas?ruta_id=$ID" 200 "$(codigo -H "$AUTH" "$GW/api/ubicacion/paradas?ruta_id=$ID")"
espera "GET /api/ubicacion/rutas?linea=252 (query string pasa el Gateway)" 1 \
  "$(curl -s -H "$AUTH" "$GW/api/ubicacion/rutas?linea=252" | json 'len(d["features"])')"

SUS=$(codigo -X POST -H "$AUTH" -H 'Content-Type: application/json' -d '{"linea":"252"}' "$GW/api/notificaciones/suscripciones")
case "$SUS" in 200|201) ok "notificaciones: suscripción a la línea 252 → $SUS" ;; *) mal "notificaciones: suscripción → $SUS" ;; esac

echo "4. Evento ruta.actualizada: Ubicación → Redis → Notificaciones"
ANTES=$(curl -s -H "$AUTH" "$GW/api/notificaciones" | json 'd["total"]')
docker compose exec -T api-ubicacion php artisan eventos:publicar-prueba 252 --descripcion="Prueba de humo $(date +%H:%M:%S)" >/dev/null
i=0; TOTAL=0
while [ $i -lt 10 ]; do
  TOTAL=$(curl -s -H "$AUTH" "$GW/api/notificaciones" | json 'd["total"]')
  [ "${TOTAL:-0}" -gt "${ANTES:-0}" ] && break
  i=$((i + 1)); sleep 1
done
[ "${TOTAL:-0}" -gt "${ANTES:-0}" ] && ok "llegó la notificación ($ANTES → $TOTAL), filtrada por el sub del JWT" || mal "no llegó la notificación"

echo
[ $FALLOS -eq 0 ] && echo "Todo en verde." || echo "$FALLOS verificación(es) fallida(s)."
exit $FALLOS

#!/bin/sh
# Pruebas de humo extremo a extremo contra el API Gateway (Definition of Done de los sprints 1 y 2).
# Requiere: docker compose up -d   ·   uso: ./scripts/smoke-test.sh [http://localhost:8080] [http://localhost:8091]
set -u
GW=${1:-http://localhost:8080}
FCM=${2:-http://localhost:8091}
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

echo "5. Usuarios: permisos por rol y cierre de sesión"
ADMIN=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"email":"admin@colcatrufis.local","password":"admin12345"}' "$GW/api/usuarios/login")
AUTH_ADMIN="Authorization: Bearer $(echo "$ADMIN" | json 'd["access_token"]')"
ID_ADMIN=$(echo "$ADMIN" | json 'd["usuario"]["id"]')
espera "GET /api/usuarios/lista sin token" 401 "$(codigo "$GW/api/usuarios/lista")"
espera "GET /api/usuarios/lista como usuario" 403 "$(codigo -H "$AUTH" "$GW/api/usuarios/lista")"
espera "GET /api/usuarios/lista como admin" 200 "$(codigo -H "$AUTH_ADMIN" "$GW/api/usuarios/lista?por_pagina=5")"
espera "GET /api/usuarios/perfil/{id} ajeno como usuario" 403 "$(codigo -H "$AUTH" "$GW/api/usuarios/perfil/$ID_ADMIN")"
ID_DEMO=$(echo "$LOGIN" | json 'd["usuario"]["id"]')
espera "GET /api/usuarios/perfil/{id} ajeno como admin" 200 "$(codigo -H "$AUTH_ADMIN" "$GW/api/usuarios/perfil/$ID_DEMO")"
OTRA=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"email":"demo@colcatrufis.local","password":"demo12345"}' "$GW/api/usuarios/login" | json 'd["refresh_token"]')
espera "DELETE /api/usuarios/sesion (el cuerpo cruza el Gateway)" 204 \
  "$(codigo -X DELETE -H "$AUTH" -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$OTRA\"}" "$GW/api/usuarios/sesion")"
espera "POST /api/usuarios/refresh con el token revocado" 401 \
  "$(codigo -X POST -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$OTRA\"}" "$GW/api/usuarios/refresh")"

echo "6. Ubicación: cercanía, radio taxis y rutas reales"
N=$(curl -s -H "$AUTH" "$GW/api/ubicacion/rutas" | json 'len(d["features"])')
[ "${N:-0}" -ge 6 ] && ok "$N rutas cargadas (mínimo 6)" || mal "solo $N rutas"
espera "GET /api/ubicacion/radiotaxis sin token" 401 "$(codigo "$GW/api/ubicacion/radiotaxis")"
TAXIS=$(curl -s -H "$AUTH" "$GW/api/ubicacion/radiotaxis")
ID_TAXI=$(echo "$TAXIS" | json 'd["features"][0]["id"] if d["type"]=="FeatureCollection" else ""')
[ -n "$ID_TAXI" ] && ok "radiotaxis: FeatureCollection con posiciones" || mal "radiotaxis: $TAXIS"
espera "PUT /radiotaxis/{id}/posicion como usuario" 403 \
  "$(codigo -X PUT -H "$AUTH" -H 'Content-Type: application/json' -d '{"lat":-17.39,"lng":-66.24}' "$GW/api/ubicacion/radiotaxis/$ID_TAXI/posicion")"
POS=$(echo "$TAXIS" | json '"{\"lat\":%s,\"lng\":%s}" % (d["features"][0]["geometry"]["coordinates"][1], d["features"][0]["geometry"]["coordinates"][0])')
espera "PUT /radiotaxis/{id}/posicion como admin" 200 \
  "$(codigo -X PUT -H "$AUTH_ADMIN" -H 'Content-Type: application/json' -d "$POS" "$GW/api/ubicacion/radiotaxis/$ID_TAXI/posicion")"
espera "GET /paradas/cercanas" 200 "$(codigo -H "$AUTH" "$GW/api/ubicacion/paradas/cercanas?lat=-17.3895&lng=-66.2405&radio=1000")"

echo "7. Notificaciones: suscripciones, historial y push hasta FCM"
HOY=$(date -u +%Y-%m-%d)
espera "GET /api/notificaciones/historial?desde=hoy" 200 "$(codigo -H "$AUTH" "$GW/api/notificaciones/historial?desde=$HOY&por_pagina=5")"
espera "GET /api/notificaciones/historial con fechas invertidas" 422 "$(codigo -H "$AUTH" "$GW/api/notificaciones/historial?desde=2026-02-01&hasta=2026-01-01")"
TOKEN_FCM="smoke-$(date +%s)-dispositivo-de-prueba"
# En variable: el sh de macOS aplica expansión de llaves a un JSON con comas dentro de "$(...)"
DISPOSITIVO="{\"token\":\"$TOKEN_FCM\",\"plataforma\":\"android\"}"
espera "POST /api/notificaciones/dispositivos" 201 \
  "$(codigo -X POST -H "$AUTH" -H 'Content-Type: application/json' -d "$DISPOSITIVO" "$GW/api/notificaciones/dispositivos")"
RUTA=$(curl -s -H "$AUTH" "$GW/api/ubicacion/rutas?linea=252" | python3 -c 'import json,sys; f=json.load(sys.stdin)["features"][0]; print(json.dumps({"id":f["id"],"geometry":f["geometry"],"descripcion":"Prueba de humo: se conserva el trazado"}))')
ID_RUTA=$(echo "$RUTA" | json 'd["id"]')
espera "PUT /api/ubicacion/rutas/$ID_RUTA como admin" 200 \
  "$(codigo -X PUT -H "$AUTH_ADMIN" -H 'Content-Type: application/json' -d "$RUTA" "$GW/api/ubicacion/rutas/$ID_RUTA")"
i=0; PUSH=0
while [ $i -lt 20 ]; do
  PUSH=$(curl -s "$FCM/mensajes" | TOKEN_FCM="$TOKEN_FCM" python3 -c 'import json,os,sys; print(sum(m["token"]==os.environ["TOKEN_FCM"] for m in json.load(sys.stdin)["mensajes"]))')
  [ "${PUSH:-0}" -ge 1 ] && break
  i=$((i + 1)); sleep 1
done
[ "${PUSH:-0}" -ge 1 ] && ok "editar ruta → evento → worker → push recibido por FCM" || mal "el push no llegó a $FCM"
espera "DELETE /api/notificaciones/dispositivos" 204 \
  "$(codigo -X DELETE -H "$AUTH" -H 'Content-Type: application/json' -d "{\"token\":\"$TOKEN_FCM\"}" "$GW/api/notificaciones/dispositivos")"
espera "DELETE /api/notificaciones/suscripciones/252" 204 "$(codigo -X DELETE -H "$AUTH" "$GW/api/notificaciones/suscripciones/252")"
espera "DELETE de una suscripción que ya no existe" 404 "$(codigo -X DELETE -H "$AUTH" "$GW/api/notificaciones/suscripciones/252")"

echo
[ $FALLOS -eq 0 ] && echo "Todo en verde." || echo "$FALLOS verificación(es) fallida(s)."
exit $FALLOS

# ColcaTrufis — plataforma de microservicios con API Gateway

Rutas de trufi, paradas y radio taxis de Colcapirhua (Cochabamba). Este repositorio contiene el
**monolito actual** y la **arquitectura objetivo de microservicios** que se construye por sprints.

```
 App Flutter ─┐                       ┌─► api-usuarios ──────► MySQL (usuarios)
              ├─► API Gateway (JWT) ──┼─► api-ubicacion ─────► PostgreSQL + PostGIS
 Panel web  ──┘     :8080             └─► api-notificaciones ► MySQL (notificaciones)
      │                                        ▲      Redis (eventos)      │
      └─► mapa base vectorial (OpenFreeMap)    └──── ruta.actualizada ─────┘
```

| Carpeta | Contenido |
|---|---|
| `services/api-usuarios` | Registro, login y emisión de JWT RS256 (Laravel 11) |
| `services/api-ubicacion` | Rutas y paradas en PostGIS, servidas como GeoJSON (Laravel 11) |
| `services/api-notificaciones` | Notificaciones y consumidor de eventos Redis (Laravel 11) |
| `gateway/` | Configuración del API Gateway (KrakenD) |
| `admin-web/` | Panel web: login JWT + mapa MapLibre GL JS |
| `fronted/` | App móvil Flutter |
| `backend/` | Monolito Laravel actual (sigue en producción) |
| `perf/` | Pruebas de carga k6 (línea base del monolito y comparación) |
| `docs/` | ADRs, contratos OpenAPI, formato de eventos, plan del sprint |

## Levantar el entorno (≈ 10 minutos la primera vez)

Requisitos: **Docker Desktop** (o Colima) con Docker Compose v2. Nada más.

```bash
git clone https://github.com/GeraldVargas/proyectotelematicos.git
cd proyectotelematicos
docker compose up -d --build
docker compose ps          # todo en "running" / "healthy"
./scripts/smoke-test.sh    # verificación extremo a extremo
```

| Servicio | URL |
|---|---|
| API Gateway | http://localhost:8080 |
| Panel web (mapa) | http://localhost:8081 |
| Swagger UI (3 APIs) | http://localhost:8090 |
| PostGIS (QGIS/DBeaver) | `localhost:5433`, usuario/clave `ubicacion` |

Usuarios de desarrollo (los crea el seeder de api-usuarios):

| Correo | Clave | Rol |
|---|---|---|
| `admin@colcatrufis.local` | `admin12345` | admin |
| `demo@colcatrufis.local` | `demo12345` | usuario |

Si un puerto ya está ocupado en tu PC: `GATEWAY_PORT=9080 ADMIN_WEB_PORT=9081 docker compose up -d`.

### Probar a mano

```bash
TOKEN=$(curl -s -X POST localhost:8080/api/usuarios/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@colcatrufis.local","password":"demo12345"}' | python3 -c 'import json,sys;print(json.load(sys.stdin)["access_token"])')

curl -i localhost:8080/api/ubicacion/rutas                                   # 401 sin token
curl -s localhost:8080/api/ubicacion/rutas -H "Authorization: Bearer $TOKEN"  # FeatureCollection
```

Evento de prueba Ubicación → Notificaciones:

```bash
curl -s -X POST localhost:8080/api/notificaciones/suscripciones \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"linea":"252"}'
docker compose exec api-ubicacion php artisan eventos:publicar-prueba 252
curl -s localhost:8080/api/notificaciones -H "Authorization: Bearer $TOKEN"
```

### Tests

```bash
docker compose exec api-usuarios php artisan test
docker compose exec api-ubicacion php artisan test        # usa la BD ubicacion_test de PostGIS
docker compose exec api-notificaciones php artisan test
```

### App Flutter contra el Gateway

```bash
cd fronted && flutter pub get
flutter run --dart-define=GATEWAY_URL=http://10.0.2.2:8080        # emulador Android
flutter run --dart-define=GATEWAY_URL=http://<IP-de-tu-PC>:8080   # celular en la misma red
flutter run --dart-define=USE_MOCKS=true                          # sin backend
```

En un celular físico, agrega la IP de tu PC en
`fronted/android/app/src/main/res/xml/network_security_config.xml` (tráfico HTTP solo en desarrollo).

### Migrar datos del monolito

Con el MySQL del monolito corriendo en tu PC (por defecto `host.docker.internal:3306`, BD `colcatrufis`;
se cambia con `MONOLITO_DB_*` en el entorno):

```bash
# Usuarios: primero 3-5 de prueba, luego todos
docker compose exec api-usuarios php artisan usuarios:migrar-monolito --email=a@x.bo --email=b@x.bo
docker compose exec api-usuarios php artisan usuarios:migrar-monolito
# Rutas: convierte los puntos de trufi_rutas en LineStrings PostGIS
docker compose exec api-ubicacion php artisan ubicacion:migrar-monolito --dry-run
docker compose exec api-ubicacion php artisan ubicacion:migrar-monolito
```

### Problemas comunes

- **El Gateway no arranca:** espera a que `api-usuarios` esté *healthy*; genera `jwk.json` al iniciar.
- **Todos los tokens dejaron de valer:** se borró el volumen `jwt-keys` y se generaron claves nuevas.
  Vuelve a iniciar sesión.
- **Mac con Apple Silicon:** la imagen de PostGIS es amd64 y corre emulada; el primer arranque es más lento.
- **Empezar de cero:** `docker compose down -v` (borra las bases de datos locales).

## Documentación

- [Plan y estado del Sprint 1](docs/sprint-1.md)
- [Decisiones de arquitectura (ADR)](docs/adr/README.md)
- Contratos OpenAPI: [usuarios](docs/openapi/usuarios.yaml) · [ubicación](docs/openapi/ubicacion.yaml) · [notificaciones](docs/openapi/notificaciones.yaml)
- [Formato del evento `ruta.actualizada`](docs/eventos/ruta.actualizada.json)
- [Pruebas de rendimiento](perf/README.md)

Datos de rutas iniciales: © colaboradores de [OpenStreetMap](https://www.openstreetmap.org/copyright)
(ODbL), relaciones de transporte mapeadas por Trufi Association.

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
| `services/api-usuarios` | Registro, login, cierre de sesión y emisión de JWT RS256 (Laravel 11) |
| `services/api-ubicacion` | Rutas, paradas y radio taxis en PostGIS, servidos como GeoJSON (Laravel 11) |
| `services/api-notificaciones` | Notificaciones, suscripciones, consumidor de eventos Redis y push FCM (Laravel 11) |
| `gateway/` | Configuración del API Gateway (KrakenD) |
| `admin-web/` | Panel web: login JWT, mapa MapLibre GL JS, radio taxis, edición de rutas y usuarios |
| `fronted/` | App móvil Flutter |
| `backend/` | Monolito Laravel actual (sigue en producción) |
| `perf/` | Pruebas de carga k6 (línea base del monolito y comparación) |
| `docs/` | ADRs, contratos OpenAPI, formato de eventos, plan del sprint |

## Levantar el entorno (≈ 10 minutos la primera vez)

Requisitos: Git, **Docker Desktop** iniciado (o Docker Engine/Colima) y Docker Compose v2.
Las APIs incluyen PHP y Composer en sus imágenes; no hace falta instalarlos en la PC.
El smoke de Windows usa PowerShell 5.1+; el de Linux/macOS usa Bash, curl y Python 3.

```bash
git clone https://github.com/GeraldVargas/proyectotelematicos.git
cd proyectotelematicos
docker compose up -d --build --wait --wait-timeout 300
docker compose ps
bash scripts/smoke-test.sh
```

En Windows, desde PowerShell, usa esta verificación en lugar del comando Bash:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/smoke-test.ps1 -Sprint2
```

El primer arranque descarga imágenes y dependencias; su duración depende de la conexión y de la
caché. Para preparar el entorno de presentación con datos completos y radio taxis en movimiento:

```bash
docker compose --profile demo up -d --build --wait --wait-timeout 300
docker compose --profile demo ps --all
docker compose logs demo-datos-ubicacion
```

`demo-datos-ubicacion` debe terminar con código 0 y mostrar «Demo lista: 22 rutas OSM, 135 paradas
OSM y 4 radio taxis demo» en una instalación nueva. Verifica asociaciones y geometrías PostGIS;
solo entonces arranca el simulador. Este actualiza los taxis cada 15 s, y los clientes consultan
su posición cada 30 s. El seeder agrega rutas ausentes y conserva las ediciones del administrador.
Si hay radio taxis importados, conserva esos datos y no fuerza la creación de taxis ficticios.
Guion de presentación y grabación: [demo del Sprint 3](docs/demo-sprint-3.md).

| Servicio | URL |
|---|---|
| API Gateway | http://localhost:8080 |
| Panel web (mapa) | http://localhost:8081 |
| Swagger UI (3 APIs) | http://localhost:8090 |
| FCM simulado (mensajes recibidos) | http://localhost:8091/mensajes |
| PostGIS (QGIS/DBeaver) | `localhost:5433`, usuario/clave `ubicacion` |

Usuarios de desarrollo (los crea el seeder de api-usuarios):

| Correo | Clave | Rol |
|---|---|---|
| `admin@colcatrufis.local` | `admin12345` | admin |
| `demo@colcatrufis.local` | `demo12345` | usuario |

Si un puerto está ocupado, cambia `GATEWAY_PORT`, `ADMIN_WEB_PORT`, `SWAGGER_PORT`,
`FCM_MOCK_PORT` o `UBICACION_DB_PORT` en el entorno. Al cambiar el puerto del Gateway, actualiza
también `gatewayUrl` en `admin-web/config.js`, los servidores de `docs/openapi/*.yaml` y
`GATEWAY_URL` en Flutter. La guía y sus ejemplos usan los puertos predeterminados de la tabla.

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

Para ver también el push, registra un dispositivo antes de publicar el evento y consulta FCM simulado:

```bash
curl -s -X POST localhost:8080/api/notificaciones/dispositivos \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"token":"mi-dispositivo-de-prueba-123"}'
curl -s localhost:8091/mensajes
```

### Tests

```bash
docker compose exec api-usuarios php artisan test
docker compose exec api-ubicacion php artisan test        # usa la BD ubicacion_test de PostGIS
docker compose exec api-notificaciones php artisan test
```

Para comprobar la demo desde una copia de fuentes limpia y bases nuevas, con Node.js 22+ y
PowerShell (`pwsh` en Linux/macOS):

```bash
node scripts/clean-verify.mjs --demo
```

El script crea un proyecto Docker separado con puertos libres, comprueba datos y movimiento,
ejecuta las tres suites y el smoke completo, y retira solo sus contenedores y volúmenes
temporales. Guarda el resultado en `docs/qa/artifacts/demo-verify.json` y el registro en
`demo-verify.log`. Conserva las bases del entorno principal. Reutiliza la caché de Docker:
no equivale a una descarga fría ni a una verificación humana por otro integrante.

### App Flutter contra el Gateway

```bash
cd fronted && flutter pub get
flutter run --dart-define=GATEWAY_URL=http://10.0.2.2:8080        # emulador Android
flutter run --dart-define=GATEWAY_URL=http://<IP-de-tu-PC>:8080   # celular en la misma red
flutter run --dart-define=USE_MOCKS=true                          # sin backend
```

En un celular físico, el build de depuración permite HTTP hacia la IP local de la PC; el build
release exige HTTPS. No uses `localhost` en el teléfono.

### Push FCM en un teléfono real

El entorno normal usa `fcm-mock`. Para cambiarlo por Firebase sin publicar secretos, sigue la
[guía de FCM real](docs/fcm-real.md). La prueba completa requiere un proyecto Firebase, una cuenta
de servicio y un Android físico.

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
# Radio taxis: importa la parada base de cada sindicato y retira los de demostración
docker compose exec api-ubicacion php artisan ubicacion:migrar-radiotaxis --dry-run
docker compose exec api-ubicacion php artisan ubicacion:migrar-radiotaxis
```

### Problemas comunes

- **El Gateway no arranca:** espera a que `api-usuarios` esté *healthy*; genera `jwk.json` al iniciar.
- **Ruta nueva del Gateway responde 404:** ejecuta `docker compose restart gateway`; lee su configuración al arrancar.
- **Todos los tokens dejaron de valer:** se borró el volumen `jwt-keys` y se generaron claves nuevas.
  Vuelve a iniciar sesión.
- **Mac con Apple Silicon:** la imagen de PostGIS es amd64 y corre emulada; el primer arranque es más lento.
- **Empezar de cero:** `docker compose down -v` (borra las bases de datos locales).

## Documentación

### Registros complementarios del 10/11/2026

- [Revisión local de realizados](docs/revision-realizados-2026-11-10.md): corrección del logout
  del panel y 71 pruebas entre Usuarios, Notificaciones, Flutter y panel; Docker no estaba
  disponible durante esa revisión.
- [Verificación de la demo del Sprint 3](docs/qa-sprint-3.md): entorno Docker aislado,
  74 pruebas de APIs, 59 comprobaciones de integración, 23 de navegador y compilaciones Flutter.

Ambos registros se conservan con sus evidencias y límites. Las pruebas con Android físico,
Firebase real y la revisión y firma por integrantes continúan pendientes.

### Avance verificado en Windows — 03/11/2026

Las tres APIs aprobaron **71 pruebas**, Flutter **20**, la integración por Gateway **59** y el
panel/Swagger en Chrome **23**. Flutter resolvió las dependencias de Firebase y compiló para web y Android ARM64 debug.
Se corrigieron refresh concurrente, un error de retirada de dispositivos FCM y logout móvil al
vencer la sesión. No se importaron datos reales del monolito.
La copia de fuentes limpias y bases nuevas arrancó en **80,52 s con caché Docker existente**;
sus 71 tests y 59 comprobaciones también pasaron sin alterar la semilla de desarrollo.

Ver [qué marcar en el informe, evidencias y pasos para demostrarlo](docs/avance-sprints-2026-11-03.md).
El [CI remoto del código base](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479)
aprobó sus siete trabajos; los cambios locales de hoy requieren su propia ejecución tras publicarlos.
Firebase real, Android físico, revisión municipal, firmas y protección de `main` siguen pendientes.

### Verificación y avance local (27/10/2026)

En Windows, desde la raíz y con Docker Desktop abierto:

```powershell
docker compose up --build
```

`Ctrl+C` apaga los servicios y conserva los datos. En otra terminal puedes comprobarlos:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/smoke-test.ps1 -Sprint2
```

El panel permite **Editar trazado** (solo admin) y buscar **Paradas cercanas**. Guardar una ruta
registra su evento de forma persistente para su posterior publicación en Redis.
Ver [evidencias y pendientes del sprint 1](docs/qa-sprint-1.md) y [avance del sprint 2](docs/sprint-2.md).
El código actualizado requiere reconstruir las imágenes con `--build`; no hace falta reinstalar
Composer en Windows. Las imágenes usan PHP 8.3.13 y los locks son compatibles con ese entorno.

Pruebas en un navegador real: `node scripts/browser-qa.mjs` (Node 22+ y Chrome).
Copia limpia, con bases de datos desechables y caché Docker existente: `node scripts/clean-verify.mjs`.

- [Plan y estado del Sprint 1](docs/sprint-1.md)
- [Estado del Sprint 2](docs/sprint-2.md) · [verificación](docs/qa-sprint-2.md)
- [Tareas y estado del Sprint 3](docs/sprint-3.md) · [verificación](docs/qa-sprint-3.md)
- [Decisiones de arquitectura (ADR)](docs/adr/README.md)
- Contratos OpenAPI: [usuarios](docs/openapi/usuarios.yaml) · [ubicación](docs/openapi/ubicacion.yaml) · [notificaciones](docs/openapi/notificaciones.yaml)
- [Formato del evento `ruta.actualizada`](docs/eventos/ruta.actualizada.json)
- [Pruebas de rendimiento](perf/README.md)

Rutas y paradas: `node scripts/importar-rutas-osm.mjs` amplía la semilla con el criterio indicado en el script.
Datos de rutas: © colaboradores de [OpenStreetMap](https://www.openstreetmap.org/copyright)
(ODbL), relaciones de transporte mapeadas por Trufi Association.

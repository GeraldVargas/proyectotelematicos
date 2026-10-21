# Sprint 1 — base de microservicios (12 al 23 de octubre de 2026)

**Sprint Goal:** la arquitectura de microservicios corre en local. El API Gateway valida JWT y enruta
hacia los 3 servicios. La API de Ubicación sirve rutas reales en GeoJSON desde PostGIS, dibujadas en
un mapa vectorial en Flutter. Queda medido el rendimiento actual del monolito como línea base.

## Acuerdos del día 1–2

| Acuerdo | Dónde quedó |
|---|---|
| JWT RS256, claims `sub`, `rol`, `exp` (+ `iss`, `iat`), header `kid: colcatrufis-1` | `services/api-usuarios/app/Services/JwtService.php` |
| Rutas `/api/{servicio}/...` | `apiPrefix` en `bootstrap/app.php` de cada servicio; `gateway/settings/endpoints.json` |
| Un `docker-compose.yml` común | raíz del repo |
| Contratos OpenAPI antes de programar | `docs/openapi/*.yaml` (Swagger UI en :8090) |

## Estado por integrante

### Salvador — DevOps / API Gateway
- [x] ADR del Gateway (Kong vs. KrakenD) → [`adr/0001`](adr/0001-api-gateway.md)
- [x] `docker-compose.yml`: gateway, 3 APIs + sus BD, PostGIS, Redis, worker, Swagger UI, panel
- [x] Enrutamiento y validación JWT con la clave pública (JWK); login/registro/refresh públicos
- [x] Monorepo + ramas ([`adr/0003`](adr/0003-repositorio-y-ramas.md)) + CI (`.github/workflows/ci.yml`)
- DoD: sin token → 401 ✔︎ · con token llega al servicio correcto ✔︎ (`scripts/smoke-test.sh`) ·
  levantar con el README ⏳ *(medir con alguien que no lo haya hecho)* · CI en cada PR ⏳ *(activar protección de `main`)*

### Fernanda — API de Usuarios
- [x] Contrato `usuarios.yaml`: `POST /registro`, `POST /login`, `GET /perfil`, `POST /refresh`
- [x] Servicio con BD propia, JWT RS256 en lugar de Sanctum; refresh token rotativo
- [x] Script `usuarios:migrar-monolito` (`--email` para probar con 3-5 usuarios, `--dry-run`)
- DoD: tests de login correcto, fallido y token expirado ✔︎ · usuario del monolito inicia sesión ✔︎ (test) ·
  migración real con 3-5 usuarios ⏳ *(requiere la BD del monolito)*

### Julio — API de Ubicación (GIS)
- [x] Esquema PostGIS: `rutas` (LineString), `paradas` (Point), `ruta_parada`; EPSG:4326, índices GiST
- [x] 4 rutas reales cargadas (líneas 201, 252, 270 y 290) con 22 paradas, desde OpenStreetMap
- [x] `GET /rutas` (FeatureCollection, filtros `linea`, `sindicato`, `estado`), `GET /rutas/{id}`, `GET /paradas`
- [x] Comando `ubicacion:migrar-monolito` para convertir `trufi_rutas` en LineStrings
- DoD: GeoJSON válido ✔︎ (tests + verificar en geojson.io) · responde por el Gateway con token ✔︎ · tests ✔︎ ·
  revisar trazados sobre el mapa con el municipio ⏳
- Fuera del sprint: `paradas/cercanas`, `radiotaxis`, `PUT /rutas/{id}`

### Eleonor — API de Notificaciones
- [x] ADR colas vs. webhooks + esquema de `ruta.actualizada` → [`adr/0002`](adr/0002-comunicacion-entre-servicios.md), [`eventos/ruta.actualizada.json`](eventos/ruta.actualizada.json)
- [x] Servicio con BD (`notificaciones`, `suscripciones`, `eventos_recibidos`) y `GET /api/notificaciones`
- [x] Contrato `notificaciones.yaml` + PoC: `eventos:publicar-prueba` (Ubicación) → Redis → `worker-notificaciones`
- DoD: el evento viaja y queda guardado ✔︎ · endpoint detrás del Gateway leyendo el `sub` ✔︎ · ADR aprobado por el equipo ⏳

### Luis — App Flutter
- [x] Capa de red: `GatewayClient` con URL base central (`--dart-define=GATEWAY_URL`), interceptor JWT,
  manejo de 401 con refresh y token en `flutter_secure_storage`
- [x] Pantalla de login contra `POST /api/usuarios/login` (+ "continuar sin iniciar sesión" a la app clásica)
- [x] Mapa con `maplibre_gl` + OpenFreeMap centrado en Colcapirhua; rutas GeoJSON con un color por línea;
  al tocar una ruta muestra número y sindicato
- [x] Mocks del contrato (`--dart-define=USE_MOCKS=true`) y tests de la capa de red
- DoD: probar en un Android físico ⏳ · `flutter pub get` + compatibilidad de `maplibre_gl` ⏳ ·
  regresión manual de las pantallas existentes ⏳

### Gerald — Panel web + QA/Rendimiento
- [x] Scripts k6 (`perf/`) para login, rutas y radio taxis; el mismo escenario sirve para el Sprint 2
- [x] Panel `admin-web/` con login JWT del Gateway (solo `admin`/`encargado`) y mapa MapLibre GL JS
- [ ] **Correr la línea base** contra el monolito con datos reales y llenar `perf/README.md`
- [ ] Ejecutar la checklist manual (abajo)

## Checklist de pruebas manuales

- [ ] `docker compose up -d --build` desde un clon limpio; anotar el tiempo
- [ ] `./scripts/smoke-test.sh` todo en verde
- [ ] Swagger UI (:8090) muestra las 3 APIs y "Try it out" funciona con un token
- [ ] Panel (:8081): login admin → mapa con 4 rutas y paradas; clic en ruta/parada muestra datos
- [ ] Panel: un usuario `usuario` (demo) **no** entra al panel
- [ ] Panel: desmarcar una ruta la oculta; "Mostrar paradas" alterna la capa
- [ ] Respuesta de `/api/ubicacion/rutas` pegada en geojson.io: trazados sobre las calles correctas
- [ ] Flutter (Android físico): login → mapa con rutas → tocar ruta → número y sindicato
- [ ] Flutter: "Continuar sin iniciar sesión" abre la app clásica y todo sigue funcionando
- [ ] Flutter: borrar el volumen `jwt-keys` y reiniciar → la app vuelve al login sin colgarse

## Demo de la Review (viernes 23)

Login en Flutter → Gateway → mapa con rutas reales desde PostGIS; después
`eventos:publicar-prueba 252` y la notificación aparece en `GET /api/notificaciones`.

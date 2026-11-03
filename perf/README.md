# Pruebas de rendimiento — monolito vs. microservicios

Escenarios en [`k6/escenarios.js`](k6/escenarios.js). Se ejecutan con k6 en Docker:

```bash
# Línea base: monolito en contenedores, con los mismos datos que los microservicios
docker compose stop
docker compose -f perf/monolito/docker-compose.yml up -d --build --wait
RED=colcatrufis-monolito_default BASE_URL=http://monolito:8000 EMAIL=admin@admin.com PASSWORD=password ./perf/run.sh monolito
docker compose -f perf/monolito/docker-compose.yml down -v

# Microservicios: mismo perfil, a través del Gateway
docker compose up -d --wait
RED=telematicos_default BASE_URL=http://gateway:8080 ./perf/run.sh microservicios
```

`RED` es la red Docker del sistema medido (`<carpeta del proyecto>_default` para los microservicios).
Úsala siempre: por el puerto publicado, Colima añade un túnel que limita la carga y provoca errores de conexión.

Cada corrida deja `resultados/<target>-<fecha>.md` (resumen, se versiona) y `.json` (detalle, no se versiona).

## Escenarios

| Escenario | % de VUs | Monolito | Microservicios |
|---|---|---|---|
| login | 20 % | `GET /admin/login` + `POST /admin/login` (sesión + CSRF) | `POST /api/usuarios/login` |
| rutas | 60 % | `GET /api/trufis/rutas/geojson` | `GET /api/ubicacion/rutas` (con JWT) |
| radiotaxis | 20 % | `GET /api/radiotaxis/paradas/geojson` | `GET /api/ubicacion/radiotaxis` (Sprint 2) |

Perfil: rampa de 20 s → **20 VUs sostenidos 2 min** → bajada 10 s. Métricas: latencia p50/p95 por
escenario, throughput (req/s) y tasa de error. Umbrales: p95 de rutas < 2 s (RNF5), errores < 1 %.

## Condiciones fijas (para que las mediciones sean comparables)

### Alcance de los datos

La BD de producción del monolito no está disponible, y no se ejecutan cargas contra el servidor público
del municipio. La línea base usa el **esquema y el código reales del monolito con los datos de la
semilla OSM** ([`monolito/sembrar.php`](monolito/sembrar.php)): las mismas 22 rutas (10 723 puntos) y 4
radio taxis que los microservicios. Es comparable con ellos, pero no representa el volumen de producción.

No cambiar entre sprints:

| Condición | Valor |
|---|---|
| Equipo | Apple M4, 16 GB, macOS 26.4.1 |
| Dónde corre el sistema | Misma PC que k6, en Colima (aarch64, 4 CPU, 6 GB) |
| Servidor PHP | `php artisan serve` con 4 workers, PHP 8.3.13, `APP_DEBUG=true` (monolito y microservicios) |
| Datos | Semilla OSM: 22 rutas, 10 723 puntos, 4 radio taxis |
| VUs / duración | 20 VUs (login 4, rutas 12, radio taxis 4), rampa 20 s, 2 min, bajada 10 s |
| Usuario de login | Monolito `admin@admin.com`; microservicios `admin@colcatrufis.local` |
| Red | Red Docker interna (variable `RED`); sin otras cargas pesadas |

**El login no es equivalente:** en el monolito son dos peticiones (formulario y POST con sesión y CSRF);
en microservicios, un POST que devuelve el JWT. En Mac, además, la imagen de PostGIS es amd64 y corre
emulada, lo que penaliza a los endpoints de Ubicación frente a MySQL nativo del monolito. Para la
comparación final conviene repetir ambas medidas en un equipo x86.

## Resultados

| Fecha | Arquitectura | Login p50 / p95 | Rutas p50 / p95 | Radio taxis p50 / p95 | req/s | Error % | Informe |
|---|---|---|---|---|---|---|---|
| 02/11/2026 | **monolito (línea base)** | 1072 / 1319 ms | 480 / 688 ms | 411 / 602 ms | 39,4 | 0,00 | [resultado](resultados/monolito-2026-11-03T00-40-18-619Z.md) |
| 02/11/2026 | microservicios (preliminar) | 597 / 746 ms | 1001 / 1259 ms | 977 / 1216 ms | 21,9 | 0,00 | [resultado](resultados/microservicios-2026-11-03T00-43-52-600Z.md) |

La fila de microservicios es un adelanto: el análisis comparativo y sus conclusiones son del Sprint 3.

La corrida [`monolito-2026-11-03T00-37-29`](resultados/monolito-2026-11-03T00-37-29-722Z.md), por el puerto
publicado, se conserva como diagnóstico y no es la línea base: 18,2 req/s y 1,96 % de errores de conexión
por el túnel de Colima, que desaparecieron al medir por la red Docker.

# Pruebas de rendimiento — monolito vs. microservicios

Escenarios en [`k6/escenarios.js`](k6/escenarios.js). Se ejecutan con k6 en Docker:

```bash
./perf/run.sh monolito          # Sprint 1: línea base (monolito en http://localhost:8000)
./perf/run.sh microservicios    # Sprint 2: mismo escenario contra el Gateway (:8080)
```

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

Completar antes de la primera corrida y **no cambiar** entre sprints:

| Condición | Valor |
|---|---|
| Equipo (CPU / RAM / SO) | _p. ej. Ryzen 5 5600H, 16 GB, Ubuntu 24.04_ |
| Dónde corre el sistema | _misma PC que k6 / otra PC en la LAN_ |
| Monolito: servidor PHP | _`php artisan serve` / nginx + php-fpm_ |
| Datos | _dump de la BD de producción del <fecha>, N trufis, M puntos_ |
| VUs / duración | 20 VUs / 2 min (variables `VUS`, `DURACION`) |
| Usuario de login | admin del panel (`EMAIL`, `PASSWORD`) |
| Red | _cableada / Wi-Fi_; sin otras cargas pesadas |

## Resultados

| Fecha | Arquitectura | Login p50 / p95 | Rutas p50 / p95 | Radiotaxis p50 / p95 | req/s | Error % |
|---|---|---|---|---|---|---|
| _pendiente_ | monolito | | | | | |

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

### Estado al 27/10/2026

La base del monolito no está accesible y no se recibió un respaldo: **la línea base real sigue
pendiente**. No se ejecutan cargas contra el servidor público del municipio.

Se añadió un ejecutor para PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File perf/run.ps1 -Target monolito
powershell -ExecutionPolicy Bypass -File perf/run.ps1 -Target microservicios -Network proyectotelematicos_default -BaseUrl http://gateway:8080 -Vus 5 -Duracion 10s -Rampa 5s -Bajada 5s -SinRadiotaxis
```

La segunda orden es una comprobación funcional parcial, no una línea base ni una comparación:
omite radio taxis, tiene 4 VUs efectivos (1 login, 3 rutas) y dura 20 s con rampas incluidas.
Sus resúmenes reales se guardan en `resultados/`. Una primera ejecución simultánea con la
construcción de otro entorno y Chrome tuvo 0 % de errores HTTP, pero p95 de rutas de 6148 ms;
**no cumplió** el umbral de 2 s. Se conserva como diagnóstico, sin ocultar ese resultado ni usarlo
como medición comparable.

Repetición sin la construcción desechable ni Chrome ejecutándose: p50/p95 de login **476/610 ms**,
p50/p95 de rutas **166/233 ms**, **14.2 req/s**, **0 % de errores HTTP**; 36 muestras de login y
251 de rutas. Cumplió los umbrales de esta prueba corta. [Resumen real](resultados/microservicios-2026-10-27T21-21-46-422Z.md).
No sustituye la línea base del monolito ni valida el perfil completo de 20 VUs/2 minutos.

Equipo observado: Intel Core i5-12450H, 7.7 GiB de RAM, Windows_NT 10.0.26200, Docker Desktop.
Para medir el monolito se necesita su BD real, acceso local, usuario de prueba y los mismos datos
y escenarios en ambas arquitecturas. Los resultados del Gateway por sí solos no prueban una mejora.

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

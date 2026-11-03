# Sprint 3 — rendimiento y preparación de entrega

## Objetivo

Comparar el monolito y la arquitectura de microservicios con la misma semilla OSM y un perfil de
carga reproducible. El alcance de este sprint es el análisis de rendimiento; las validaciones en
Android físico, Firebase real, firmas y protección remota de la rama se conservan como pendientes
externos de los sprints anteriores.

## Método

Las dos corridas se ejecutaron el 02/11/2026 en un Apple M4, 16 GB, usando la red interna de
Docker/Colima, la misma semilla (22 rutas, 10 723 puntos y 4 radio taxis), 20 VUs durante 2 min,
rampa de 20 s y bajada de 10 s. El reparto fue 4 VUs para login, 12 para rutas y 4 para radio
taxis. El detalle reproducible está en [perf/README.md](../perf/README.md).

| Métrica | Monolito | Microservicios | Variación microservicios |
|---|---:|---:|---:|
| Login p50 / p95 | 1 072 / 1 319 ms | 597 / 746 ms | -44 % / -43 % |
| Rutas p50 / p95 | 480 / 688 ms | 1 001 / 1 259 ms | +109 % / +83 % |
| Radio taxis p50 / p95 | 411 / 602 ms | 977 / 1 216 ms | +138 % / +102 % |
| Throughput | 39,4 req/s | 21,9 req/s | -44 % |
| Errores HTTP | 0,00 % | 0,00 % | sin regresión |

Evidencias: [monolito](../perf/resultados/monolito-2026-11-03T00-40-18-619Z.md) y
[microservicios](../perf/resultados/microservicios-2026-11-03T00-43-52-600Z.md).

## Conclusiones

- **RNF5 superado para este perfil:** p95 de rutas es menor a 2 s en ambas arquitecturas; los
  microservicios quedan en 1 259 ms y no registran errores.
- **Login mejora en microservicios:** el JWT del Gateway completa el flujo más rápido que el login
  de formulario con CSRF y sesión del monolito. No es una equivalencia exacta: el flujo del
  monolito hace dos peticiones.
- **Las lecturas GIS empeoran, pero siguen dentro del objetivo:** Gateway, JWT y PostGIS añaden
  coste. Además, PostGIS amd64 se ejecutó emulado en el Apple M4, por lo que no debe atribuirse toda
  la diferencia únicamente a la arquitectura.
- **No hay evidencia aún para RNF6 (100 usuarios concurrentes):** estas corridas miden 20 VUs.
  No se debe declarar ese requisito como cumplido hasta repetir la carga con 100 VUs y medir los
  mismos umbrales.

## Próximos pasos medibles

1. Repetir ambos objetivos en el mismo equipo x86 con 20 VUs para eliminar la emulación de PostGIS.
2. Ejecutar 100 VUs, conservando proporción 20 % login / 60 % rutas / 20 % radio taxis, y aceptar
   solo si rutas p95 < 2 s y errores < 1 %.
3. Perfilar `GET /api/ubicacion/rutas` y `GET /api/ubicacion/radiotaxis` antes de optimizar:
   confirmar uso de índices PostGIS, tamaño del GeoJSON y tiempos de Gateway.
4. Incorporar los nuevos informes en `perf/resultados/` y actualizar esta tabla con la fecha,
   hardware y configuración exactos.

## Estado

El análisis comparativo del Sprint 3 está documentado. La validación de 100 usuarios, el Android
físico, Firebase real, las firmas y la protección de `main` permanecen pendientes de recursos o
acciones externas.

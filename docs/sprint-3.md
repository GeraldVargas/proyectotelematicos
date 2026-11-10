# Sprint 3 — rendimiento y preparación de entrega

## Objetivo

Estabilizar el sistema, preparar una demo reproducible y completar las instrucciones de ejecución,
manteniendo la comparación monolito/microservicios ya realizada. Esta revisión cubre las tareas
del Sprint 3 del PDF, páginas 25–27, hasta antes de «Definición de Done del Sprint 3 (entrega
final)». Las tareas marcadas «Realizado» en el PDF se conservan como antecedentes; no se atribuyen
como trabajo nuevo.

## Pendientes del PDF atendidos el 10/11/2026

| Responsable | Tarea del Sprint 3 | Trabajo y estado |
|---|---|---|
| Salvador | Revisar y completar el README principal: clonar → levantar → probar | Documentación actualizada y comandos comprobados con fuentes limpias y bases nuevas. Falta la revisión personal por otro integrante exigida en el entregable. |
| Salvador | Preparar Docker Compose en modo demo | Realizado y ejecutado. El perfil `demo` prepara y valida la semilla antes de iniciar el simulador. |
| Julio | Preparar el conjunto completo de rutas y paradas en la BD demo | Realizado: 22 rutas OSM y 135 paradas publicadas por el Gateway; cuatro radio taxis demo y movimiento comprobados. La repetición conserva ediciones. |
| Luis | Resolver bugs pendientes de Flutter; app estable en Android físico | Revalidados los 20 tests y las compilaciones web y APK debug ARM64. La comprobación de estabilidad en Android físico queda pendiente: ADB no detectó ningún dispositivo conectado. |
| Luis | Grabar video del flujo completo en `docs/demo-flutter.mp4` | Pendiente de Android físico y FCM real. Se dejó un guion de 160 segundos y los comandos de captura; no se generó un video simulado. |
| Luis | Instrucciones definitivas de compilación y variables en `fronted/README.md` | README actualizado: requisitos, Gateway por plataforma, seis variables, mocks, FCM, pruebas y compilación. Falta la verificación personal por otro integrante. |

Evidencia de las comprobaciones y revisión manual pendiente: [QA del Sprint 3](qa-sprint-3.md).
Preparación, presentación y captura: [guion de demo](demo-sprint-3.md).

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

![Gráfica comparativa de p95 y throughput](qa/artifacts/rendimiento-sprint-3.svg)

La gráfica separa latencia p95 (menos es mejor) de throughput (más es mejor), para no mezclar
milisegundos y solicitudes por segundo en la misma escala.

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

El análisis comparativo previo se conserva y el modo demo y su conjunto de datos se comprobaron
con bases nuevas. Los dos README están actualizados; requieren validación por otro integrante
para completar literalmente esos entregables. La estabilidad en Android físico y el video con
push requieren conectar un teléfono y configurar Firebase real. La validación de 100 usuarios
sigue siendo una limitación del estudio de rendimiento, no una prueba ejecutada en esta revisión.

# Sprint 3 — rendimiento y preparación de entrega

Ver también la [revisión de realizados del 10/11](revision-realizados-2026-11-10.md):
contraste de las evidencias, pruebas locales ejecutadas y corrección del panel.

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

Se conservan además los antecedentes de la revisión local sin Docker, con sus límites
y pendientes originales. Las firmas y la protección de `main` siguen pendientes.

### Estado registrado en la revisión sin Docker

El análisis comparativo del Sprint 3 está documentado. La validación de 100 usuarios, el Android
físico, Firebase real, las firmas y la protección de `main` permanecen pendientes de recursos o
acciones externas.

## Verificación documental sin Docker — 10/11/2026

En una PC Windows donde Docker Desktop no estaba disponible se comprobó, sin ejecutar cargas, que:

- `perf/run.sh` no tiene errores de sintaxis.
- Los Compose del monolito y de los microservicios son válidos (`docker compose config -q`).
- Los dos informes de la comparación usan el mismo perfil: 20 VUs, 2 min sostenidos, rampa de
  20 s, bajada de 10 s y reparto 4/12/4 para login/rutas/radio taxis.
- Los resultados registrados son coherentes con RNF5: p95 de rutas de 688 ms (monolito) y
  1 259 ms (microservicios), con 0,00 % de errores en ambos casos.
- El escenario admite `VUS=100` y contiene los umbrales de rutas p95 menor a 2 s y errores
  menores al 1 %, pero esa carga **no se ejecutó** sin Docker/k6.
- Las 21 rutas declaradas por el Gateway están documentadas en OpenAPI y tienen su ruta
  correspondiente en los servicios de Usuarios, Ubicación o Notificaciones.
- Se revisaron 31 documentos Markdown y no tienen enlaces internos rotos.
- Los 117 archivos PHP de los tres servicios y el JavaScript del panel pasaron la validación de
  sintaxis; tampoco contienen marcadores técnicos pendientes (`TODO`, `FIXME`, `XXX`, `HACK` o
  `BUG`).
- El contrato de Usuarios contiene los siete endpoints previstos, ejemplos completos en sus cuatro
  solicitudes con cuerpo y ejemplos de respuesta en sus siete operaciones exitosas.

Esta revisión permite declarar realizados el perfil reproducible, la documentación y el análisis
comparativo de 20 VUs. No sustituye una nueva medición ni demuestra RNF6 (100 usuarios
concurrentes).

## Perfil previo a optimización sin Docker — 10/11/2026

La revisión estática deja preparada la optimización posterior, pero no reemplaza un perfilador en
ejecución ni `EXPLAIN ANALYZE` sobre PostGIS:

| Elemento | Hallazgo | Implicación |
|---|---|---|
| `GET /api/ubicacion/rutas` | `rutas.geom` tiene índice GiST y `linea` índice B-tree. La consulta convierte todas las geometrías con `ST_AsGeoJSON` y devuelve la colección completa. | El índice espacial no participa en un listado completo; la principal variable a medir es la serialización y el tamaño de respuesta. |
| Datos de rutas | La semilla GeoJSON ocupa 306 659 bytes y contiene las 22 rutas usadas en las pruebas. | Es un volumen pequeño para la demostración; no representa aún datos de producción. |
| `GET /api/ubicacion/radiotaxis` | `radiotaxis.geom` tiene índice GiST. La consulta filtra por `disponible`, ordena por ID y serializa puntos GeoJSON. | Con cuatro radio taxis demo no se justifica optimizar a ciegas. Si crece el volumen, medir un índice sobre `disponible` o uno parcial antes de añadirlo. |
| Gateway | La configuración enruta ambos endpoints directamente al servicio de Ubicación. | El coste real del Gateway solo puede medirse con el entorno levantado. |

Por tanto, el diagnóstico de código y datos queda realizado. Para declarar una optimización de
rendimiento realizada faltan las mediciones de tiempos del Gateway/PostGIS y, si procede, un cambio
respaldado por una nueva corrida k6.

La semilla se verificó además mediante análisis local: es una `FeatureCollection` válida con 22
`LineString`, 10 723 puntos y cero coordenadas fuera de los rangos geográficos. Esta validación no
ejecuta PostGIS ni sustituye las comprobaciones de geometrías almacenadas en la base de datos.

## Tabla de rendimiento conservada de la revisión sin Docker

La revisión sin Docker registró que el PDF de planificación solo contenía tablas de Sprint 1
y Sprint 2 y propuso las filas siguientes. La revisión de demo identifica tareas de Sprint 3
en las páginas 25–27; véase la tabla al inicio de este documento. Se conserva este cuadro
como antecedente del análisis de rendimiento. Las menciones a Docker no disponible describen
aquella revisión, no anulan las evidencias de demo incorporadas:

| Tarea de Sprint 3 | Estado que se debe poner | Evidencia |
|---|---|---|
| Línea base y comparación monolito–microservicios con semilla OSM | **Realizado** | Dos corridas comparables de 20 VUs; informes en `perf/resultados/`. |
| Cuadro comparativo, gráfica y conclusiones de rendimiento | **Realizado** | Tabla y porcentajes contrastados contra los resultados k6. |
| RNF5: rutas con p95 menor a 2 s | **Realizado para el perfil de 20 VUs** | Monolito: 688 ms; microservicios: 1 259 ms; 0,00 % de errores. |
| Validación de la semilla OSM para rendimiento | **Realizado** | 22 rutas, 10 723 puntos y cero coordenadas inválidas. |
| Perfil previo a optimización de rutas y radio taxis | **Realizado documentalmente** | Índices, tamaño de datos, serialización y trayecto del Gateway revisados. |
| Repetir la comparación en PC x86 | **Pendiente: requiere Docker/k6** | No se levantaron contenedores en esta PC. |
| Validar 100 usuarios concurrentes (RNF6) | **Pendiente: requiere Docker/k6** | El escenario admite `VUS=100`, pero no se ejecutó. |
| Medir Gateway y PostGIS con perfilador/`EXPLAIN ANALYZE` | **Pendiente: requiere Docker/PostGIS activo** | La revisión actual fue estática. |

## Unificación de la interfaz Flutter

El login y la sesión guardada abren ahora la interfaz clásica. La ruta antigua `/mapa`
también lleva a esa pantalla, sin un segundo frontend. Se conserva el diseño del mapa y
sidebar y se incorpora **Notificaciones** (suscripciones por línea) junto al acceso de sesión.
Las rutas, paradas y radiotaxis del Gateway se adaptan al formato de los componentes clásicos;
los radiotaxis se refrescan cada 30 segundos. La navegación sin sesión conserva la API pública
del monolito. No se mezclan sus identificadores con los del Gateway al consultar detalles.

El panel Docker conserva la edición de trazado y la publicación transaccional de eventos.
Su formulario de rutas ahora permite sindicato, horario, tarifa y frecuencia; dispone además
de edición de nombre, descripción y coordenadas de paradas, y nombre, sindicato, teléfono y
posición de radio taxis disponibles. Son ediciones de registros existentes, no altas de nuevas
rutas o paradas. Las relaciones parada–ruta se conservan. Las escrituras requieren rol admin.
En Flutter, «Actualizar datos» invalida el catálogo y carga los cambios; los detalles muestran
los metadatos de las rutas y aceptan el horario textual del Gateway.

No se levantó Docker en esta revisión por la restricción de espacio del equipo. Las pruebas
PostGIS añadidas quedan preparadas para CI; no se consideran ejecutadas localmente. El modo
simulado verifica la adaptación Flutter sin sustituir una prueba con contenedores reales.

Verificación local de la unificación: 23 pruebas Flutter existentes y de adaptación pasaron;
se añadió y pasó además la prueba de recarga de metadatos y horario (24 casos en total).
Pasaron 7 pruebas Node del panel (sesión y formularios), la sintaxis PHP de controladores,
rutas y pruebas nuevas, y el análisis Flutter sin errores (194 avisos/recomendaciones).
La compilación web completa no se pudo confirmar: el intento agotó la memoria durante
la comprobación WebAssembly. No se declara validación visual ni ejecución Docker/PostGIS.
Las nuevas pruebas PostGIS se incluyen en el job existente de CI y las pruebas del panel
se incorporaron al job Node; no se ejecutó CI desde esta sesión.

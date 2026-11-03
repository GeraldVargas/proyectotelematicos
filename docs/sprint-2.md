# Sprint 2 — estado al 02/11/2026

Todo lo que depende solo de código y de Docker está implementado y verificado ([evidencia](qa-sprint-2.md)).
**El sprint no se declara terminado:** faltan los puntos que requieren un teléfono, un proyecto de
Firebase, datos o decisiones de terceros y las firmas del equipo.

## Estado por integrante

Leyenda: ✅ hecho y verificado · 🟡 implementado, verificación pendiente · ⏳ bloqueado por algo externo

| Responsable | Tarea | Estado | Nota |
|---|---|---|---|
| Salvador | `composer.lock` y Dockerfiles | ✅ | Construcción en frío de las imágenes: 7 min 9 s en Colima |
| Salvador | Smoke test completo | ✅ | `scripts/smoke-test.sh` todo en verde, dos veces seguidas |
| Salvador | Protección de `main` | ⏳ | Requiere la cuenta de Gerald, dueño del repositorio: [pasos](proteccion-main.md) |
| Salvador | Contenedor FCM mock | ✅ | `fcm-mock`, sin credenciales |
| Salvador | CI: tests, cobertura y k6 | 🟡 | `ci.yml` publica cobertura y k6 en el resumen del PR; se verá al ejecutarse en GitHub |
| Fernanda | Migración real de usuarios | ⏳ | Requiere la BD del monolito; comando probado con BD simulada |
| Fernanda | `DELETE /sesion`, `GET /perfil/{id}` | ✅ | |
| Fernanda | `GET /lista` (solo admin) | ✅ | |
| Fernanda | 15+ tests | ✅ | 21 casos |
| Julio | Tests contra PostGIS real | ✅ | 25 casos |
| Julio | Captura de trazados | ✅ | [Panel](qa/artifacts/panel-mapa.png). Falta la verificación manual en geojson.io |
| Julio | `paradas/cercanas`, `PUT rutas/{id}` | ✅ | Ya estaban hechas |
| Julio | 6+ rutas con paradas | ✅ | 22 rutas y 135 paradas de OpenStreetMap |
| Julio | Revisión con el municipio | ⏳ | Requiere a la contraparte municipal |
| Julio | `GET /radiotaxis` | ✅ | **Con datos de demostración**: ver [ADR 0005](adr/0005-push-fcm-y-radiotaxis.md) |
| Julio | `ubicacion.yaml` | ✅ | 1.2.0 |
| Eleonor | Aprobación del ADR 0002 | ⏳ | Cada integrante firma con su cuenta: [tabla](adr/0002-comunicacion-entre-servicios.md) |
| Eleonor | `POST`/`DELETE` suscripciones | ✅ | |
| Eleonor | Integración FCM | 🟡 | Probado hasta `fcm-mock`; la entrega real requiere Firebase |
| Eleonor | `GET /historial` | ✅ | |
| Eleonor | 10+ tests | ✅ | 23 casos |
| Luis | Prueba en Android físico y regresión | ⏳ | Requiere Flutter y un teléfono |
| Luis | Capa de radio taxis (30 s) | 🟡 | Código escrito, **sin compilar** |
| Luis | Push FCM en la app | 🟡 | Código escrito, **sin compilar** y sin Firebase |
| Luis | Pantalla de suscripciones | 🟡 | Código escrito, **sin compilar** |
| Luis | `fronted/README.md` | ✅ | |
| Gerald | Línea base k6 del monolito | ✅ | Reproducible, con datos OSM y no de producción: [resultados](../perf/README.md) |
| Gerald | Radio taxis en el panel | ✅ | Actualización cada 30 s |
| Gerald | Administración de rutas | ✅ | Ya estaba hecha |
| Gerald | Lista de usuarios en el panel | ✅ | |
| Gerald | Checklist manual firmada | 🟡 | Partes automáticas ejecutadas; faltan las manuales y la firma: [checklist](qa-sprint-2.md) |

## Definición de Done

| Criterio | Estado |
|---|---|
| Todos los endpoints del informe implementados y en Swagger | ✅ |
| `docker compose up -d --build` desde cero sin errores | ✅ |
| La app funciona en Android físico con rutas, paradas y radio taxis | ⏳ |
| El push llega al dispositivo al editar una ruta | ⏳ Llega hasta FCM simulado |
| Línea base k6 documentada y reproducible | ✅ |
| Checklist manual completa | 🟡 |

## Qué debe hacer cada quien

1. **Luis:** `cd fronted && flutter pub get && flutter test`, confirmar `pubspec.lock` y probar en Android.
   El código Dart solo pasó una revisión de sintaxis: la primera compilación puede pedir ajustes.
2. **Eleonor y Luis:** crear el proyecto de Firebase y seguir [`fronted/README.md`](../fronted/README.md) y el ADR 0005.
3. **Fernanda y Julio:** conseguir un respaldo de la BD del monolito para `usuarios:migrar-monolito`,
   `ubicacion:migrar-monolito` y `ubicacion:migrar-radiotaxis`.
4. **Julio:** revisar los trazados con el municipio. La línea 270 solo tiene 1 parada en OSM.
5. **Todos:** firmar el ADR 0002 y la checklist. **Gerald (dueño del repositorio en GitHub):** proteger `main`.

## Hallazgo en el monolito

`backend/app/Models/TrufiRuta.php` declara `class Trufiruta`, y `SindicatoRadiotaxiParada.php` y otros
tienen la misma diferencia de mayúsculas. Funciona en Windows y macOS, pero en Linux esos endpoints
responden 500. No se cambió porque el monolito sigue en producción; `perf/monolito/Dockerfile` lo
resuelve solo para la medición.

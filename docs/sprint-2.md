# Sprint 2 — estado al 03/11/2026

Revisión posterior: [comprobación de realizados del 10/11](revision-realizados-2026-11-10.md).
Incluye pruebas repetidas, una corrección del panel y los límites por Docker no disponible.

Las APIs, el panel y el flujo hasta FCM simulado están implementados y verificados ([evidencia](qa-sprint-2.md)).
Flutter resolvió dependencias, aprobó 20 pruebas y compiló para web y Android ARM64 debug. El análisis no encontró errores;
mantiene advertencias históricas. Ver [qué marcar en el informe y cómo demostrarlo](avance-sprints-2026-11-03.md).
**El sprint no se declara terminado:** faltan los puntos que requieren un teléfono, un proyecto de
Firebase, datos o decisiones de terceros y las firmas del equipo.

## Estado por integrante

Leyenda: ✅ hecho y verificado · 🟡 implementado, verificación pendiente · ⏳ bloqueado por algo externo

| Responsable | Tarea | Estado | Nota |
|---|---|---|---|
| Salvador | `composer.lock` y Dockerfiles | ✅ | Construyen en Windows con PHP 8.3.13; caché y volúmenes conservados. Evidencia anterior en Colima: 7 min 9 s |
| Salvador | Smoke test completo | ✅ | PowerShell en Windows: 59 comprobaciones, también tras reconstruir los fixes |
| Salvador | Protección de `main` | ⏳ | Requiere la cuenta de Gerald, dueño del repositorio: [pasos](proteccion-main.md) |
| Salvador | Contenedor FCM mock | ✅ | `fcm-mock`, sin credenciales |
| Salvador | CI: tests, cobertura y k6 | ✅ | [7 jobs aprobados para `e9e9bf9`](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479). Los nuevos fixes locales aún requieren publicación y CI |
| Fernanda | Migración real de usuarios | ⏳ | Requiere la BD del monolito; comando probado con BD simulada |
| Fernanda | `DELETE /sesion`, `GET /perfil/{id}` | ✅ | |
| Fernanda | `GET /lista` (solo admin) | ✅ | |
| Fernanda | 15+ tests | ✅ | 22 casos, incluida rotación atómica del refresh; dos solicitudes simultáneas por MySQL: 200/401 |
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
| Eleonor | 10+ tests | ✅ | 24 casos; un 404 genérico de FCM conserva el dispositivo y reintenta |
| Luis | Prueba en Android físico y regresión | ⏳ | Flutter instalado y funcionando; falta teléfono y backend histórico para la regresión completa |
| Luis | Capa de radio taxis (30 s) | 🟡 | Compila en web y Android ARM64, con pruebas de servicio; falta validación física |
| Luis | Push FCM en la app | 🟡 | Dependencias y APK aprobados; entrega real pendiente de Firebase y teléfono |
| Luis | Pantalla de suscripciones | 🟡 | Compila en web y Android ARM64; suscribir/desuscribir probado en servicios y mocks; falta revisión física |
| Luis | `fronted/README.md` | ✅ | |
| Gerald | Línea base k6 del monolito | ✅ | Reproducible, con datos OSM y no de producción: [resultados](../perf/README.md) |
| Gerald | Radio taxis en el panel | ✅ | Actualización cada 30 s |
| Gerald | Administración de rutas | ✅ | Ya estaba hecha |
| Gerald | Lista de usuarios en el panel | ✅ | |
| Gerald | Checklist manual firmada | 🟡 | Partes automáticas ejecutadas; faltan las manuales y la firma: [checklist](qa-sprint-2.md) |

## Definición de Done

| Criterio | Estado |
|---|---|
| Endpoints previstos de los sprints 1 y 2 implementados y en Swagger | ✅ |
| `docker compose up -d --build` con fuentes limpias y bases nuevas | ✅ 80,52 s con caché; no es descarga fría ni clon remoto |
| La app funciona en Android físico con rutas, paradas y radio taxis | ⏳ |
| El push llega al dispositivo al editar una ruta | ⏳ Llega hasta FCM simulado |
| Línea base k6 documentada y reproducible | ✅ |
| Checklist manual completa | 🟡 |

## Qué debe hacer cada quien

1. **Luis:** probar en Android físico siguiendo [`fronted/README.md`](../fronted/README.md).
   `flutter pub get`, las 20 pruebas y las compilaciones web/APK ARM64 ya pasaron en esta PC; el lock está actualizado.
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

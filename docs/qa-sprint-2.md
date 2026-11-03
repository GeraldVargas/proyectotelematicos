# Verificación del sprint 2 — actualizada al 03/11/2026

## Comprobación del 03/11/2026 en Windows

Docker Desktop se inició y se reconstruyeron las imágenes, conservando los volúmenes del entorno
principal. No se migraron datos reales. [Guía para marcar las tablas del informe](avance-sprints-2026-11-03.md).

| Comprobación | Resultado actual |
|---|---|
| Usuarios / Ubicación / Notificaciones | **22 / 25 / 24 casos aprobados**, 71 en total |
| Aislamiento | Usuarios/Notificaciones: SQLite en memoria; Ubicación: `ubicacion_test` en PostGIS |
| Copia limpia sin dependencias, bases nuevas y proyecto Docker aislado | **80,52 s de arranque con caché existente**, 71 tests y 59 comprobaciones aprobados; usuarios de semilla conservados y limpieza correcta: [JSON](qa/artifacts/clean-verify.json) |
| PowerShell `scripts/smoke-test.ps1 -Sprint2` | **59 comprobaciones**; ejecución final tras reconstruir los cambios |
| Chrome `node scripts/browser-qa.mjs` | **23 comprobaciones**; [JSON](qa/artifacts/browser-qa.json) |
| Swagger: «Try it out» con JWT | Rutas, radio taxis, lista de usuarios e historial devuelven 200 |
| Refresh simultáneo por Gateway y MySQL | Solo una solicitud renueva: 200/401. Logout revoca el nuevo refresh |
| PostGIS | 22 rutas, 135 paradas, 0 geometrías inválidas |
| Flutter | `pub get` correcto, **20 pruebas aprobadas**, compilaciones web release y APK debug Android ARM64 aprobadas |
| Análisis Flutter completo | 0 errores, 11 warnings históricos en `home_screen.dart` y 202 avisos informativos; no se presenta como lint limpio |
| CI remoto | [7 jobs aprobados para el código base `e9e9bf9`](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479); comprobado mediante GitHub CLI, solo lectura |

La ejecución remota corresponde al mismo commit que el `HEAD` local antes de esta revisión.
Pasaron tests/lint de las tres APIs, contratos, sintaxis del panel, Flutter e integración Docker/k6.
Los fixes y la documentación de hoy aún no están publicados ni cubiertos por ese resultado remoto.

Se corrigieron refresh concurrente, retirada de dispositivos por 404 genérico, logout móvil con
rotación durante el cierre y actualización de la pantalla de login después de salir de ella.
Se amplió la prueba de Swagger y se aisló el puerto FCM del script de verificación limpia.

Capturas actuales: [mapa](qa/artifacts/panel-mapa.png), [radio taxis](qa/artifacts/panel-radiotaxis.png),
[usuarios](qa/artifacts/panel-usuarios.png), [edición](qa/artifacts/panel-ruta-guardada.png),
[Swagger Usuarios](qa/artifacts/swagger-usuarios.png), [Swagger radio taxis](qa/artifacts/swagger-radiotaxis.png)
y [Swagger historial](qa/artifacts/swagger-historial.png).

El APK se generó con Gateway `http://10.0.2.2:8080` para emulador ARM64, sin Firebase configurado.
Está en `fronted/build/app/outputs/flutter-apk/app-debug.apk` (127 514 745 bytes).
Las compilaciones y las pruebas de servicios no sustituyen Android físico ni Firebase real.
La checklist manual al final sigue reservada para quien ejecute y firme esos pasos.

## Evidencia anterior — 02/11/2026

Equipo: Apple M4, 16 GB, macOS 26.4.1, Docker en Colima (4 CPU, 6 GB). Entorno reconstruido con `docker compose down -v` y bases vacías.

| Comprobación | Resultado |
|---|---|
| API de Usuarios, en contenedor con SQLite de pruebas (migraciones aplicadas también en MySQL al arrancar) | 21 casos en verde (mínimo pedido: 15) |
| API de Ubicación, contra PostGIS real | 25 casos en verde |
| API de Notificaciones, con FCM simulado | 23 casos en verde (mínimo pedido: 10) |
| Lint con Laravel Pint en los 3 servicios | Sin observaciones |
| `scripts/smoke-test.sh`, por el Gateway, repetible | 34 comprobaciones en verde |
| Push completo: editar ruta → outbox → Redis → worker → `fcm-mock` | Recibido, con `notification` y `data` en formato FCM v1 |
| Token no registrado (`invalido-...`) en `fcm-mock` | El dispositivo se elimina solo |
| `scripts/browser-qa.mjs`, Chrome real: panel y Swagger | 20 comprobaciones en verde, 3 ejecuciones seguidas: [resultado](qa/artifacts/browser-qa.json) |
| 3 contratos OpenAPI con Redocly | Válidos |
| `radiotaxis:simular` en `--profile demo` | Las posiciones cambian y siguen sobre las rutas |
| Geometrías en PostGIS | 22 rutas, 135 paradas, 0 inválidas (`ST_IsValid`) |
| Carga k6 con el perfil completo | [Línea base y resultados](../perf/README.md), 0 % de errores |

Capturas: [mapa con 22 rutas](qa/artifacts/panel-mapa.png), [radio taxis](qa/artifacts/panel-radiotaxis.png),
[usuarios](qa/artifacts/panel-usuarios.png), [guardado de ruta](qa/artifacts/panel-ruta-guardada.png),
[Swagger](qa/artifacts/swagger-rutas.png).

## Pendientes registrados el 02/11/2026 y actualización

| Qué | Por qué | Quién |
|---|---|---|
| Android físico y regresión | En Windows ya se ejecutaron `pub get`, análisis, 20 tests y compilaciones web/APK ARM64; sigue pendiente la prueba en teléfono | Luis |
| Push en un teléfono y contra Google | Falta el proyecto de Firebase | Eleonor y Luis |
| `scripts/smoke-test.ps1 -Sprint2` | **Resuelto en Windows: 59 comprobaciones**; falta confirmar ejecución remota en CI | CI |
| Workflow de GitHub Actions | **Resuelto para la base `e9e9bf9`: 7 jobs aprobados**. Los cambios locales del 03/11 necesitan nueva publicación y ejecución | Salvador |
| Migraciones reales desde el monolito | No hay acceso a su BD; los comandos se probaron con BD simulada | Fernanda y Julio |
| Revisión municipal de trazados | Requiere a la contraparte municipal | Julio |

## Checklist manual

Marcar y firmar quien la ejecute. Las capturas automáticas no reemplazan esta revisión.

| # | Paso | Resultado | Responsable |
|---|---|---|---|
| 1 | Pegar `/api/ubicacion/rutas` en geojson.io: los trazados siguen las calles | ☐ | |
| 2 | Panel: editar un trazado, guardar y ver el cambio en el mapa | ☐ | |
| 3 | Panel: `docker compose --profile demo up -d` y ver moverse los radio taxis en 30 s | ☐ | |
| 4 | Panel: un administrador ve la lista de usuarios; un encargado no | ☐ | |
| 5 | Swagger (:8090): «Try it out» de un endpoint nuevo de cada API con un token | ☐ | |
| 6 | Flutter en Android: login → mapa → rutas, paradas y radio taxis | ☐ | |
| 7 | Flutter: activar una línea en «Avisos por línea», editar esa ruta y recibir el push | ☐ | |
| 8 | Flutter: cerrar sesión y comprobar que ya no llegan avisos | ☐ | |
| 9 | Flutter: «Continuar sin iniciar sesión» abre la app clásica sin errores | ☐ | |

Firma: ______________________   Fecha: ______________

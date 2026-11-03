# Verificación del sprint 2 — 02/11/2026

## Pruebas automáticas ejecutadas

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

## No ejecutado

| Qué | Por qué | Quién |
|---|---|---|
| `flutter analyze`, `flutter test`, compilación y Android físico | Flutter no está instalado en este equipo. El Dart solo pasó el analizador de sintaxis de `dart format` | Luis |
| Push en un teléfono y contra Google | Falta el proyecto de Firebase | Eleonor y Luis |
| `scripts/smoke-test.ps1 -Sprint2` | PowerShell no está disponible en este equipo; se ejecutará en el CI | CI |
| Workflow de GitHub Actions | Los cambios no están publicados; solo se validó su sintaxis YAML | Salvador |
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

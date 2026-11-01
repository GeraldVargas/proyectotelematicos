# Verificación técnica del sprint 1 — 27/10/2026

Esta evidencia corresponde a ejecución automática local. No sustituye la revisión municipal,
la firma de integrantes ni una prueba en Android físico.

| Comprobación | Evidencia |
|---|---|
| Usuarios: login, registro, expiración, refresh y migración simulada | 11 casos PHPUnit |
| Ubicación sobre PostGIS real | 6 casos originales y 11 nuevos del sprint 2 |
| Notificaciones y deduplicación | 5 casos PHPUnit |
| Flutter: interceptor, refresh y fixtures | 6 casos; fixtures con 4 rutas y 22 paradas OSM |
| JWT, CORS, identidad y flujo de eventos por KrakenD | 40 comprobaciones de `scripts/smoke-test.ps1 -Sprint2` |
| Panel y Swagger: mapa, filtros, detalles, arrastre, quitar/restablecer puntos, guardado y permisos | 15 comprobaciones en Chrome: [resultado](qa/artifacts/browser-qa.json) |
| Swagger: tres contratos y Try it out de rutas con JWT | [Captura](qa/artifacts/swagger-rutas.png) |
| Fuentes sin dependencias y bases nuevas | [Resultado, tiempo e aislamiento](qa/artifacts/clean-verify.json) |

Capturas: [mapa](qa/artifacts/panel-mapa.png), [detalles](qa/artifacts/panel-detalle.png),
[rechazo de rol](qa/artifacts/panel-rol-rechazado.png), [guardado desde el panel](qa/artifacts/panel-ruta-guardada.png).
El JSON de cada ejecución indica si terminó correctamente; no se interpreta la existencia de
un archivo como una prueba aprobada.

## Reproducción

```powershell
docker compose up -d --build --wait
powershell -ExecutionPolicy Bypass -File scripts/smoke-test.ps1 -Sprint2
node scripts/browser-qa.mjs
node scripts/clean-verify.mjs
cd fronted
flutter test
```

El smoke envía avisos de desarrollo y guarda el mismo trazado para comprobar la edición sin
cambiar el recorrido. La verificación limpia usa otro proyecto Docker con volúmenes nuevos y
puertos libres; comprueba que los tests no alteran los usuarios de desarrollo y retira solo sus
contenedores/volúmenes. Chrome usa un perfil temporal independiente.

Se encontró y corrigió el uso involuntario de la BD de desarrollo durante PHPUnit: ahora el
arranque de tests fija SQLite en memoria para Usuarios/Notificaciones y `ubicacion_test` para GIS,
antes de ejecutar RefreshDatabase, independientemente de las variables heredadas de Docker.

La copia limpia contiene los archivos versionables del árbol de trabajo, sin vendor/cachés de
Flutter. No es un clon remoto ni una descarga fría: reutiliza la caché Docker existente. El objetivo
de menos de 15 minutos desde descarga fría sigue sin demostrarse; en esta PC las descargas
iniciales fueron muy lentas.

## Pendientes externos para cerrar formalmente el sprint

- [ ] Base o respaldo del monolito. `host.docker.internal:3306/colcatrufis` no está accesible;
  no se migraron usuarios/rutas reales. La migración probada usa datos sintéticos.
- [ ] Línea base k6 del monolito con datos reales y condiciones fijadas. Una prueba del Gateway
  no constituye esa línea base ni permite calcular una mejora relativa.
- [ ] Android físico: login, mapa, selección y regresión de las pantallas clásicas.
- [ ] Revisión municipal de los trazados; los datos OSM no equivalen a esa validación.
- [ ] Aprobación formal del ADR 0002 y firma humana de la checklist.
- [ ] Protección de `main`: la cuenta local puede hacer push, pero no tiene permiso de administrador.
  Ver [instrucciones para el propietario](proteccion-main.md).
- [ ] Ejecución de GitHub Actions después de publicar los cambios. El workflow preparado y las
  comprobaciones locales no se presentan como una ejecución remota aprobada.

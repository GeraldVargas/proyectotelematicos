# Verificación técnica del sprint 1 — 27/10/2026

Esta evidencia corresponde a ejecución automática local. No sustituye la revisión municipal,
la firma de integrantes ni una prueba en Android físico.

## Actualización del 03/11/2026

La tabla siguiente conserva los resultados **históricos del 27/10**. Las capturas y
`browser-qa.json` enlazados fueron actualizados en verificaciones posteriores, por lo que sus
recuentos actuales pueden ser mayores. El estado vigente está en
[avance del 03/11](avance-sprints-2026-11-03.md) y [QA del sprint 2](qa-sprint-2.md).

Hoy, en Windows/Docker, pasaron 22 tests de Usuarios, 25 de Ubicación y 24 de Notificaciones
(71 en total), 20 tests Flutter, 59 comprobaciones del smoke de PowerShell en dos ejecuciones
y 23 comprobaciones de Chrome, incluyendo Swagger de las tres APIs. PostGIS contiene 22 rutas,
135 paradas y cero geometrías inválidas. Son resultados del conjunto actual de ambos sprints.
El entorno principal reconstruyó imágenes conservando volúmenes. Después, una copia de fuentes
sin dependencias arrancó con bases nuevas en un proyecto Docker aislado en **80,52 s**, usando
caché existente. Pasaron sus 71 tests y 59 comprobaciones, la semilla quedó intacta y el entorno
desechable se retiró correctamente: [resultado actual](qa/artifacts/clean-verify.json).
Flutter compiló para web y Android ARM64 debug. No se hizo un clon remoto ni descarga fría.

La consulta remota del 03/11 confirmó la
[ejecución CI 41564765479](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479):
los siete jobs de la base `e9e9bf91589b517a70926133b21b57f94df1f58e` terminaron correctamente.
Ese commit coincide con el HEAD local consultado; la ejecución fue creada el **02/11/2026
a las 22:13 (Bolivia)**. Esta aprobación no incluye las nuevas correcciones y documentos
locales del 03/11, que aún no se publicaron.

## Evidencia histórica del 27/10/2026

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

## Reproducción de las comprobaciones

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

La copia limpia histórica contiene los archivos versionables del árbol de trabajo, sin vendor/cachés de
Flutter. No es un clon remoto ni una descarga fría: reutiliza la caché Docker existente. El objetivo
de menos de 15 minutos desde descarga fría sigue sin demostrarse; en esta PC las descargas
iniciales fueron muy lentas.

## Pendientes externos para cerrar formalmente el sprint

- [ ] Base o respaldo del monolito para la migración manual. No se migraron usuarios/rutas
  reales; los comandos se probaron con datos sintéticos.
- [ ] Línea base k6 con datos de producción, si se exige ese alcance. **Ya existe una línea
  base del monolito con esquema real y semilla OSM**, con condiciones documentadas en
  [perf/README.md](../perf/README.md); no se repitió hoy en Windows ni representa producción.
- [ ] Android físico: login, mapa, selección y regresión de las pantallas clásicas.
- [ ] Revisión municipal de los trazados; los datos OSM no equivalen a esa validación.
- [ ] Aprobación formal del ADR 0002 y firma humana de la checklist.
- [ ] Protección de `main`: la cuenta local puede hacer push, pero no tiene permiso de administrador.
  Ver [instrucciones para el propietario](proteccion-main.md).
- [ ] Publicar los cambios locales del 03/11 y comprobar su propia ejecución de GitHub Actions.
  El CI remoto de la base ya está aprobado; ese resultado no cubre las modificaciones actuales
  sin publicar.

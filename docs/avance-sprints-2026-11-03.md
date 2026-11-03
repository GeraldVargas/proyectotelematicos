# Avance comprobado de los sprints 1 y 2 — 03/11/2026

Referencia: `telematicosInformeZ.docx.pdf`, páginas 17–26. Se revisó el código que ya existía,
se corrigieron fallos y se ejecutaron comprobaciones en esta PC Windows con Docker Desktop.
No se importaron datos del monolito ni se modificó el PDF original.

## Qué se corrigió en esta revisión

- **Refresh de Usuarios:** dos solicitudes simultáneas ya no pueden consumir el mismo refresh
  token. La rotación usa una actualización condicional dentro de una transacción.
- **FCM:** un error HTTP 404 genérico conserva el dispositivo y permite reintentar. Solo el
  código `UNREGISTERED` retira un token no registrado.
- **Logout de Flutter:** si el access token vence durante el cierre, también se revoca el
  refresh nuevo que se haya generado. La pantalla de login comprueba `mounted` antes de actualizarse.
- **Dependencias móviles:** `flutter pub get` resolvió las dependencias de Firebase y actualizó
  el lock y los registros de plugins generados.
- **QA de navegador:** ahora se ejecutan consultas desde Swagger de las tres APIs, incluidos
  radio taxis, lista de usuarios e historial. Se renovaron las capturas y el resultado JSON.
- **Verificación limpia:** el mock FCM recibe un puerto libre propio y ese puerto se pasa al
  smoke test, para que no choque con el entorno principal.

## Resultados en este equipo

| Comprobación | Resultado |
|---|---|
| Construcción y arranque Docker conservando volúmenes | Correctos; APIs y bases saludables |
| Fuentes limpias y bases nuevas, proyecto Docker aislado | **Arranque en 80,52 s con caché existente**; 71 tests y 59 comprobaciones aprobados, semilla intacta y limpieza correcta: [resultado](qa/artifacts/clean-verify.json) |
| Usuarios / Ubicación / Notificaciones | 22 / 25 / 24 casos aprobados; **71 en total** |
| Ubicación | Tests contra `ubicacion_test` en PostGIS, separado de la base de desarrollo |
| Usuarios y Notificaciones | Tests con SQLite en memoria |
| Integración por Gateway en PowerShell | **59 comprobaciones**, también después de reconstruir los fixes |
| Dos refresh simultáneos por Gateway y MySQL | Un `200` y un `401`; logout `204`; refresh posterior `401` |
| Chrome: panel y Swagger | **23 comprobaciones**: [resultado](qa/artifacts/browser-qa.json) |
| Geometrías de la base de desarrollo | **22 rutas, 135 paradas, cero geometrías inválidas** |
| Flutter: red, sesión, avisos y mocks | **20 pruebas aprobadas** |
| Compilación Flutter | **Web release y APK debug Android ARM64 aprobados**. APK en `fronted/build/app/outputs/flutter-apk/app-debug.apk` |
| CI remoto del código base `e9e9bf9` | **7 jobs aprobados**, [ejecución en GitHub](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479). Los cambios de esta revisión aún no están publicados |

Las pruebas crean sesiones y avisos de desarrollo y guardan el mismo trazado para comprobar
la edición. No convierten los radio taxis de demostración en vehículos con GPS real.

## Qué escribir en las tablas del informe

Usa **Realizado y verificado localmente (03/11/2026)** para los puntos aprobados. Si una fila
reúne una implementación local y una validación externa, conserva ambos estados en esa fila.

### Sprint 1 — páginas 17–20

| Fila o grupo | Qué marcar |
|---|---|
| Gateway, Compose, JWT, contratos y servicios | **Realizado** |
| Smoke test completo | **Realizado: 59 comprobaciones en Windows** |
| Regenerar locks y resolver Dockerfiles | **Realizado: las imágenes construyen con PHP 8.3.13**. No indicar PHP 8.2 como versión ejecutada |
| Tests de Usuarios, Ubicación y Notificaciones | **Realizado**; actualizar cantidades a 22, 25 y 24 |
| Rutas y paradas OSM, endpoints y mapas | **Realizado: 22 rutas y 135 paradas**; revisión municipal pendiente |
| PoC Ubicación → Redis → Notificaciones | **Realizado** |
| Panel, login, roles, edición y Swagger | **Realizado**, con capturas en `docs/qa/artifacts/` |
| Capa de red y mocks Flutter | **Realizado: 20 pruebas aprobadas** |
| `flutter pub get` / compatibilidad Android | **Dependencias resueltas y compilación ARM64 aprobada**; prueba física pendiente |
| Línea base k6 | **Realizado con semilla OSM, no con datos de producción**; ver [condiciones](../perf/README.md) |
| Arranque limpio / tiempo menor a 15 minutos | **Realizado desde copia de fuentes sin dependencias: 80,52 s con caché y bases nuevas**. Un clon remoto con descarga fría y la revisión por otro integrante siguen sin demostrarse |
| Migración real de usuarios | **Pendiente: ejecución manual con respaldo del monolito** |
| CI remoto | **Realizado para la versión base**: siete jobs aprobados. Los nuevos cambios de hoy requieren publicación y una nueva ejecución |
| Protección de `main` | **Pendiente del administrador**; la cuenta conectada tiene `WRITE`, sin administración |
| Aprobación ADR, revisión municipal, Android físico y firma de checklist | **Pendiente de validación humana** |

La tabla de cierre de la página 20 ya dice «Bien» en Android físico y geojson.io, mientras
las tareas anteriores dicen que faltan. No conservar «Bien» sin esa evidencia: escribir
**Pendiente de prueba física** y **Pendiente de revisión en geojson.io**, respectivamente.

### Sprint 2 — páginas 21–26

| Responsable y tarea | Qué marcar |
|---|---|
| Salvador: locks/Dockerfiles, smoke y FCM mock | **Realizado y verificado localmente** |
| Salvador: fila que combina smoke y protección de `main` | **Parcial: smoke realizado; protección pendiente del administrador** |
| Salvador: CI con cobertura y k6 | **Realizado para la versión base: siete jobs aprobados en GitHub**. Falta publicar los fixes de hoy y volver a comprobar CI |
| Fernanda: migración de cinco usuarios reales | **Pendiente/manual**, fuera de esta revisión |
| Fernanda: logout, perfil por ID, lista admin y 15+ tests | **Realizado: 22 casos** |
| Julio: tests PostGIS / geojson.io | **25 casos aprobados y capturas del panel; revisión en geojson.io pendiente** |
| Julio: cercanía, edición con evento y documentación | **Realizado** |
| Julio: seis o más rutas y revisión municipal | **Parcial: 22 rutas/135 paradas cargadas; revisión municipal pendiente** |
| Julio: radio taxis | **API y panel verificados con datos de demostración**; validación Android pendiente |
| Eleonor: aprobación formal ADR 0002 | **Pendiente de firmas personales** |
| Eleonor: suscripción, desuscripción, historial y 10+ tests | **Realizado: 24 casos** |
| Eleonor: FCM | **Implementado y verificado hasta FCM simulado; Firebase/teléfono pendientes** |
| Luis: prueba y regresión en Android físico | **Pendiente de teléfono** |
| Luis: capa de radio taxis y pantalla de suscripciones | **Compiladas para web y Android ARM64, con pruebas de servicios; validación física pendiente** |
| Luis: push FCM en la app | **Código y dependencias compilan para Android; pendiente configuración Firebase y entrega en teléfono** |
| Luis: instrucciones de compilación | **Realizado**, ver [README Flutter](../fronted/README.md) |
| Gerald: línea base k6 | **Realizado con OSM, no con volumen de producción** |
| Gerald: radio taxis, edición de rutas y usuarios en panel | **Realizado**, ver capturas y prueba Chrome |
| Gerald: checklist manual completa y firmada | **Parcial: comprobaciones automáticas hechas; pasos físicos y firma pendientes** |

No marcar como terminado todo el sprint 2: su definición de Done exige Android físico,
push al dispositivo y checklist completa.

## Cómo demostrarlo

Desde la raíz, para arrancar o volver a encender el entorno:

```powershell
docker compose up -d --build --wait
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/smoke-test.ps1 -Sprint2
node scripts/browser-qa.mjs
```

1. Abrir [panel](http://localhost:8081), entrar con `admin@colcatrufis.local` / `admin12345`.
   Mostrar rutas, paradas, **Editar trazado**, **Paradas cercanas** y **Ver usuarios**.
2. Para movimiento de demostración: `docker compose --profile demo up -d simulador-radiotaxis`.
   Los radio taxis se desplazan cada 15 s y el panel consulta cada 30 s.
3. Abrir [Swagger](http://localhost:8090), obtener JWT con login, pulsar **Authorize** y probar
   `/api/usuarios/lista`, `/api/ubicacion/radiotaxis` y `/api/notificaciones/historial`.
4. Mostrar [FCM simulado](http://localhost:8091/mensajes) tras ejecutar el smoke. Esto demuestra
   el envío del backend al mock; la entrega al teléfono necesita Firebase real.
5. Ejecutar Flutter según su README y completar la [checklist](qa-sprint-2.md) con un teléfono.

El APK generado apunta a `http://10.0.2.2:8080` para un **emulador ARM64**, sin Firebase configurado.
Para un teléfono físico, autorizar la IP de esta PC en `network_security_config.xml` y reconstruir:

```powershell
cd fronted
flutter build apk --debug --target-platform android-arm64 --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080
```

Para detener conservando datos: `docker compose --profile demo stop`.

## Qué falta y cómo cerrarlo

- **Migración:** trabajar manualmente con un respaldo del monolito, probar primero con cinco
  usuarios y verificar login; no se ejecutó ningún comando de importación real aquí.
- **Firebase y teléfono:** configurar app y cuenta de servicio del mismo proyecto, activar
  una línea en **Avisos por línea**, editarla y comprobar recepción y cierre de sesión.
- **Trazados:** exportar el GeoJSON, revisar en geojson.io y validar con la contraparte municipal.
- **Equipo:** cada integrante aprueba el ADR con su propia cuenta; quien ejecute la revisión
  manual registra su nombre y firma. No se firmó en nombre de nadie.
- **GitHub:** el CI de la versión base ya pasó. Publicar los cambios locales de hoy y comprobar
  su nueva ejecución; aplicar [protección de main](proteccion-main.md) con la cuenta administradora.
  Solo se consultó GitHub; no se modificó la configuración remota.

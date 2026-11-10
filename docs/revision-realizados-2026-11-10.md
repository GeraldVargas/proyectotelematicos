# Revisión de tareas marcadas como realizadas — 10/11/2026

Se contrastaron las marcas de [Sprint 1](sprint-1.md), [Sprint 2](sprint-2.md),
[Sprint 3](sprint-3.md) y el [avance del 03/11](avance-sprints-2026-11-03.md)
con el código, las pruebas ejecutables y los informes guardados. Se encontró y corrigió
un fallo en el cierre de sesión del panel. Las comprobaciones que necesitan Docker no
pudieron repetirse: el motor devuelve `Docker Desktop is unable to start`.

## Resultado por grupo

| Tarea marcada como realizada | Resultado de esta revisión | Evidencia y límite |
|---|---|---|
| Compose, Gateway y contratos | Implementados; verificación estática aprobada | Ambos Compose pasan `config -q`. Los 21 pares método/ruta del [Gateway](../gateway/settings/endpoints.json) están en los tres contratos OpenAPI y en las rutas Laravel. No se repitió el arranque ni la validación JWT en el Gateway real. |
| Usuarios: registro, login, perfil, refresh, logout y lista por rol | Verificado nuevamente | **22 pruebas, 89 aserciones aprobadas**, con SQLite en memoria y claves RSA de prueba. Incluye migración simulada, rotación y permisos. |
| Scripts de migración del monolito | Implementados | Existen los comandos de usuarios, rutas y radio taxis. La migración de usuarios se probó con datos simulados; importar datos reales sigue pendiente de un respaldo. |
| Ubicación: rutas, paradas, cercanía, edición, radio taxis y eventos | Implementados; pruebas PostGIS no repetidas | Se revisaron controladores, consultas, validaciones, índices y publicación mediante outbox. La evidencia anterior conserva **25 pruebas PostGIS aprobadas** en [clean-verify.json](qa/artifacts/clean-verify.json). |
| Semilla OSM y mocks | Verificados nuevamente | **22 rutas LineString, 10 723 puntos y 135 paradas únicas**, sin coordenadas de rutas fuera de rango. Las pruebas Flutter comprueban las fixtures y sus relaciones. No sustituye revisión municipal. |
| Notificaciones, suscripciones, historial y FCM simulado | Verificado nuevamente a nivel de servicio | **24 pruebas, 96 aserciones aprobadas**, con SQLite y HTTP simulado. Incluye deduplicación, reintentos, OAuth simulado y eliminación de dispositivos. El recorrido Redis → worker → FCM mock no se repitió. |
| Flutter: cliente Gateway, JWT, refresh, logout, servicios y mocks | Verificado nuevamente | **20 pruebas aprobadas**. No se volvieron a compilar web/APK; no se probó Android físico ni la regresión del backend histórico. |
| Panel: login, mapa, edición, radio taxis y usuarios | Implementados; corrección de logout completada | **5 nuevas pruebas de sesión aprobadas**. Las otras interacciones conservan las [23 comprobaciones de navegador del 03/11](qa/artifacts/browser-qa.json); no se repitieron contra un backend activo. |
| CI, arranque limpio y smoke | Configuración y evidencia previa presentes | Se incorporan las nuevas pruebas del panel al CI. Los siete jobs remotos, los 80,52 s de arranque y las 59 comprobaciones corresponden a la revisión anterior; no validan automáticamente estos cambios locales. |
| Comparación de rendimiento y RNF5 para 20 VUs | Evidencia documental contrastada | Los dos informes usan 20 VUs, reparto 4/12/4, 2 min, rampa 20 s y bajada 10 s. Rutas p95: **688/1 259 ms**, ambos por debajo de 2 s. No se ejecutó una carga nueva ni se demostró RNF6. |
| ADR e informe final | Documentación presente | Las secciones del [informe final](informe-final/README.md), ADR, gráfica y guion de demo existen. La aprobación del equipo y la integración al documento final conservan su estado pendiente. |

## Corrección realizada

El botón **Salir** del [panel](../admin-web/app.js) enviaba un único DELETE con el access token
guardado e ignoraba un 401. Si el token había vencido, borraba la sesión del navegador pero dejaba
vigente el refresh en el servidor.

Ahora espera una renovación que ya esté en curso y, si el DELETE recibe 401, renueva una sola vez
y revoca el refresh recién emitido. Sin conexión o con credenciales rechazadas sigue cerrando la
sesión local. Las [pruebas de regresión](../scripts/panel-session.test.mjs) ejecutan el código real
del panel con DOM y HTTP simulados; se reprodujo el fallo antes del cambio y pasan después.
Estas pruebas también se ejecutarán en el [CI](../.github/workflows/ci.yml).

## Comprobaciones ejecutadas

- Usuarios: **22/22**, Notificaciones: **24/24**, Flutter: **20/20**, panel: **5/5**.
  Son **71 casos entre estas cuatro suites**; no confundirlos con las 71 pruebas de APIs del
  03/11, que incluían las 25 de Ubicación y no incluían Flutter ni estas pruebas del panel.
- Sintaxis PHP: **121 archivos** de los servicios, excluidas dependencias y almacenamiento,
  sin errores; JavaScript del panel y su configuración también válidos.
- YAML de los tres contratos leído correctamente y correspondencia estructural de las 21
  operaciones comprobada. Esto no equivale a ejecutar el validador completo de OpenAPI/KrakenD.
- Configuración Compose de microservicios y monolito válida.

Se usó PHP **8.2.12 de XAMPP** para las pruebas locales de esta fecha. Los contenedores conservan
PHP 8.3.13. Se instalaron las dependencias con sus locks existentes y se prepararon los archivos
de entorno locales a partir de los ejemplos, sin importar datos reales.

Para repetir las pruebas PHP en esta PC, XAMPP necesita señalar su configuración OpenSSL:

```powershell
$env:OPENSSL_CONF = 'D:/xamp/php/extras/ssl/openssl.cnf'
Push-Location services/api-usuarios
php artisan test
Pop-Location
Push-Location services/api-notificaciones
php artisan test
Pop-Location
Push-Location fronted
flutter test --no-pub
Pop-Location
node --test scripts/panel-session.test.mjs
```

En otro equipo, usar la ruta de su instalación OpenSSL si PHP no la detecta automáticamente.

## Pendientes que no se cierran con esta revisión

- Recuperar el motor Docker para repetir PostGIS, smoke, navegador, arranque limpio y k6.
- Medir 100 usuarios concurrentes y perfilar Gateway/PostGIS antes de afirmar optimizaciones.
- Configurar Firebase real, disponer de Android físico y comprobar recepción de push y regresión.
- Conseguir un respaldo del monolito, revisar trazados con el municipio y en geojson.io.
- Completar firmas personales, protección de la rama y una ejecución remota de CI con estos cambios.

Las marcas de realizado se sostienen con el alcance indicado en la tabla; no se declara terminado
el flujo integral ni se convierten las evidencias históricas en ejecuciones nuevas.

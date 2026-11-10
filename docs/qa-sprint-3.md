# Verificación del Sprint 3 — 10/11/2026

Alcance: tareas de estabilización y demo de las páginas 25–27 del PDF del informe, hasta antes
de la definición de Done final. Las verificaciones automáticas y las comprobaciones humanas se
registran por separado.

## Pruebas ejecutadas

Equipo: Windows, Intel Core i5-12450H, 7,7 GiB de RAM. Docker Desktop con caché existente.
Flutter 3.41.2 y Dart 3.11.0. No se actualizó el SDK ni se cambiaron las versiones del lock.

| Comprobación | Resultado |
|---|---|
| `node scripts/clean-verify.mjs --demo` | Correcto; copia de fuentes del árbol actual, dependencias en imágenes y volúmenes nuevos, separados del entorno principal |
| Arranque aislado del perfil demo | 70,24 s con caché Docker; no fue una descarga fría ni un clon remoto |
| Preparación y consultas por Gateway | 22 rutas, 135 paradas, cuatro radio taxis; movimiento periódico verificado |
| API Usuarios / Ubicación / Notificaciones | 22 / 28 / 24: 74 pruebas aprobadas |
| Regresión de la preparación de demo | Tres casos nuevos: base vacía; repetición sin duplicar ni sobrescribir ediciones; restauración de ruta ausente y detección de paradas desvinculadas |
| Integración: autenticación, roles, GIS, edición, eventos y push simulado | 59 comprobaciones aprobadas |
| Aislamiento de PHPUnit | Conservados los dos usuarios y la clave de demo antes y después de las pruebas |
| Limpieza de verificación | Retirados exclusivamente los contenedores, red y volúmenes del proyecto temporal; bases principales conservadas |
| `flutter pub get` y `flutter test --no-pub` | Dependencias resueltas con el lock existente; 20 pruebas aprobadas |
| `flutter build web --no-pub --dart-define=GATEWAY_URL=http://localhost:8080` | Compilación JavaScript aprobada, 229,5 s; conserva avisos de incompatibilidad del ensayo Wasm y de fuente Cupertino |
| `flutter build apk --debug --no-pub --target-platform android-arm64 --dart-define=GATEWAY_URL=http://10.0.2.2:8080` | APK generado, 127 514 745 bytes; Gradle 87,9 s. Configurado para emulador, sin Firebase |
| `flutter analyze --no-pub` | 213 observaciones; salida 1. Persisten warnings y avisos informativos anteriores; el análisis no se declara limpio |
| Laravel Pint de los dos archivos PHP nuevos | Dos archivos aprobados |
| Chrome: panel y Swagger de las tres APIs | 23 comprobaciones aprobadas después de terminar las compilaciones |

Evidencias: [resultado reproducible](qa/artifacts/demo-verify.json) y
[registro completo](qa/artifacts/demo-verify.log). El script original sin `--demo` conserva el
perfil normal y sus archivos de evidencia anteriores.

El preparador también terminó correctamente en el entorno principal, con sus volúmenes
existentes. La primera revisión del navegador coincidió con la compilación Flutter: el Gateway
registró `context deadline exceeded` en rutas/paradas, y Windows informó 382 220 KiB de memoria
libre. Se detuvieron temporalmente los contenedores conservando sus datos para completar las
compilaciones antes de repetir esa revisión. Este incidente se registra por separado de la
ejecución aislada aprobada.
El [registro inicial](qa/artifacts/sprint-3/browser-qa-inicial.json) y su
[captura](qa/artifacts/sprint-3/panel-fallo.png) conservan esa observación.

La repetición del navegador aprobó login, capas y detalles, edición y guardado, cercanía,
radio taxis, lista de usuarios, rechazo de rol y revocación de sesión. Swagger ejecutó consultas
con JWT en las tres APIs. [Resultado](qa/artifacts/sprint-3/browser-qa.json) y capturas:
[mapa](qa/artifacts/sprint-3/panel-mapa.png), [detalle](qa/artifacts/sprint-3/panel-detalle.png),
[ruta guardada](qa/artifacts/sprint-3/panel-ruta-guardada.png),
[usuarios](qa/artifacts/sprint-3/panel-usuarios.png),
[Swagger Usuarios](qa/artifacts/sprint-3/swagger-usuarios.png).
La captura inicial del mapa ahora espera a que haya trazados renderizados, para no registrar
un fotograma anterior a la carga de las capas.

Para repetir estas capturas en una carpeta independiente, desde PowerShell en la raíz:

```powershell
$env:QA_ARTIFACTS_DIR = 'docs/qa/artifacts/sprint-3'
node scripts/browser-qa.mjs
```

Tras ajustar la consulta de validación para limitarla al conjunto OSM, se reconstruyó Ubicación
y se repitieron sus 28 pruebas y el Pint de los dos archivos nuevos: ambos aprobados.
El perfil demo quedó levantado en el proyecto principal, con las bases existentes conservadas.

## Checklist humana del Sprint 3

Completar los resultados y la firma después de ejecutar los pasos. No se da por realizada una
revisión humana usando capturas o pruebas automáticas.

| Paso | Estado / responsable |
|---|---|
| Otro integrante sigue el README principal desde clonar hasta probar | Pendiente de nombre, fecha y resultado |
| Otro integrante sigue `fronted/README.md` y compila con su Gateway | Pendiente de nombre, fecha y resultado |
| Login → mapa → seleccionar ruta → radio taxis en Android físico | Pendiente; `adb devices -l` no encontró ningún dispositivo |
| Suscribirse a la línea 252 → emitir evento → recibir FCM real | Pendiente de teléfono y configuración Firebase |
| Grabar y revisar `docs/demo-flutter.mp4`, duración menor a 3 min | Pendiente; [guion y comandos preparados](demo-sprint-3.md) |
| Regresión visual completa y firma de la checklist final | Pendiente de quien la ejecute |

Revisor del README principal: __________________ Fecha: __________ Resultado: __________

Revisor del README Flutter: ____________________ Fecha: __________ Resultado: __________

Pruebas en Android (modelo/versión): ____________ Responsable: __________ Fecha: __________

Firma de revisión manual: ______________________ Fecha: __________

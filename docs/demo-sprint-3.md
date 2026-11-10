# Preparación de la demo del Sprint 3

Desde la raíz, con Docker Desktop iniciado:

```powershell
docker compose --profile demo up -d --build --wait --wait-timeout 300
docker compose --profile demo ps --all
docker compose logs demo-datos-ubicacion
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/smoke-test.ps1 -Sprint2
```

El preparador termina en `Exited (0)`; el simulador y los workers quedan ejecutándose. En una
base nueva hay 22 rutas, 135 paradas y cuatro radio taxis de demostración. Los datos OSM provienen
del archivo versionado y no requieren consultar OpenStreetMap durante el arranque. El mapa base
OpenFreeMap sí requiere Internet. Repetir la preparación conserva los trazados editados.
Si el preparador informa paradas desvinculadas o geometrías inválidas, revisa esos registros
antes de presentar; no borra ni reemplaza automáticamente las ediciones para ocultar el problema.

## Flujo de presentación

1. Abre http://localhost:8081 con `admin@colcatrufis.local` / `admin12345`; muestra rutas,
   paradas, detalles y radio taxis. El movimiento se consulta cada 30 s.
2. Abre Swagger en http://localhost:8090 y demuestra login y consulta protegida con JWT.
3. En Flutter entra con `demo@colcatrufis.local` / `demo12345`, toca una ruta y abre
   «Avisos por línea». Activa la línea 252 y vuelve al mapa.
4. Publica el evento desde otra terminal:

   ```powershell
   docker compose exec -T api-ubicacion php artisan eventos:publicar-prueba 252 --descripcion="Demostración del Sprint 3"
   ```

5. Para ver el aviso en el teléfono, configura previamente Firebase real siguiendo
   [la guía FCM](fcm-real.md). Con el backend predeterminado, el mensaje se verifica en
   `http://localhost:8091/mensajes` después de registrar un dispositivo simulado mediante la API;
   ese modo no entrega mensajes al teléfono. El smoke realiza esa comprobación automáticamente.

La preparación de datos del perfil demo no crea suscripciones por cuenta del usuario. Activa
la línea desde la app antes de emitir el evento. Para demostrarlo en Swagger, usa
`POST /api/notificaciones/suscripciones` con `{"linea":"252"}` y consulta después el historial
con el mismo usuario autenticado.

## Video Flutter de menos de tres minutos

Requiere un Android físico conectado por USB, depuración autorizada, Firebase real y la app
compilada con `GATEWAY_URL` apuntando a la IP LAN de la PC. Comprueba `adb devices -l` y
`flutter devices`. Con más de un dispositivo usa `adb -s SERIAL` en cada comando.

Guion de unos 160 segundos:

| Tiempo | Acción visible |
|---|---|
| 0–25 s | Abrir la app e iniciar sesión |
| 25–65 s | Mostrar el mapa, rutas, paradas y radio taxis |
| 65–90 s | Tocar una ruta y mostrar su detalle |
| 90–120 s | Activar los avisos de la línea 252 y volver al mapa |
| 120–160 s | Publicar el evento desde la PC y mostrar el aviso recibido en primer plano |

En una terminal inicia la grabación, realiza el guion y al terminar descarga el archivo:

```powershell
adb -d shell screenrecord --time-limit 160 /sdcard/colcatrufis-sprint-3.mp4
adb -d pull /sdcard/colcatrufis-sprint-3.mp4 docs/demo-flutter.mp4
```

La primera orden ocupa la terminal hasta terminar la captura. Emite el evento desde otra
terminal. Reproduce el MP4 y comprueba que se vean las cuatro acciones pedidas y la notificación
real antes de marcar la tarea como realizada. Si falla el push, corrige la configuración y
repite la toma. El video no se considera generado por disponer de este guion.

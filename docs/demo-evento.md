# Guion de demo: actualización de ruta y notificación

Este guion prepara la tarea del Sprint 3 «Preparar el script de demo para el evento:
`eventos:publicar-prueba 252` → notificación aparece en app Flutter y en panel».

## Preparación

1. Levantar el entorno: `docker compose up -d --build --wait`.
2. Iniciar sesión por Gateway con `demo@colcatrufis.local` / `demo12345`.
3. Crear una suscripción a la línea 252 mediante `POST /api/notificaciones/suscripciones` con
   cuerpo `{"linea":"252"}`.
4. Registrar un dispositivo si se desea probar el mock FCM mediante
   `POST /api/notificaciones/dispositivos`.

## Evento

Ejecutar en la raíz del repositorio:

```powershell
docker compose exec api-ubicacion php artisan eventos:publicar-prueba 252
```

Después, consultar `GET /api/notificaciones` por Swagger o el Gateway. El aviso debe aparecer en
el historial del usuario. En local, el envío a FCM se verifica en `http://localhost:8091/mensajes`.

## Límites de la demostración

El mock demuestra el flujo Ubicación → Redis → Notificaciones → FCM simulado. Recibir el push en
la app exige configurar Firebase y probar con un teléfono físico. Este guion está preparado, pero
su ejecución final sigue pendiente mientras Docker no esté disponible.

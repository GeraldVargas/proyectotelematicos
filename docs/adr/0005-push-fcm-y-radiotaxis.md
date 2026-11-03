# ADR 0005 — Push por FCM con bandeja de salida y radio taxis

- **Estado:** implementada localmente; pendiente de revisión del equipo.
- **Fecha:** 2026-11-02 (Sprint 2).

## Push

Al consumir `ruta.actualizada`, Notificaciones crea en una misma transacción cada notificación y una
fila en `envios_push` por dispositivo. `worker-notificaciones` envía los pendientes después de cada
evento y, si no hay eventos, cada 5 s como máximo. Llamar a FCM dentro de la transacción la haría
depender de un servicio externo, y enviar sin registrar perdería el push si el worker cae.

| Respuesta de FCM | Acción |
|---|---|
| 2xx | `enviado` |
| 404 (`UNREGISTERED`) | Se elimina el dispositivo y sus envíos pendientes |
| Otro error o sin conexión | Reintento a los 10, 20, 40 y 80 s; al quinto intento, `fallido` con `ultimo_error` |

Un fallo de FCM nunca revierte ni oculta la notificación: sigue disponible en
`GET /api/notificaciones` y en `/historial`.

Si otra cuenta inicia sesión en el mismo teléfono, el token pasa a esa cuenta y se descartan sus
envíos pendientes. Al cerrar sesión, la app elimina el token con `DELETE /dispositivos`.

### Entornos

| Entorno | Configuración | Qué demuestra |
|---|---|---|
| Local y CI | `fcm-mock` (por defecto), sin credenciales; los mensajes se ven en `http://localhost:8091/mensajes` | Backend hasta el borde de FCM, con el formato de la API HTTP v1 |
| Real | `FCM_URL=https://fcm.googleapis.com`, `FCM_PROJECT_ID` y `FCM_CREDENCIALES=/secrets/firebase.json` (cuenta de servicio en `secrets/`, carpeta ignorada por Git) | Entrega en el teléfono |

**Sin un proyecto de Firebase, la entrega en un teléfono no está probada.** El flujo OAuth2 de la
cuenta de servicio se verificó con respuestas simuladas, no contra Google.

No se usa el SDK de Firebase para PHP: solo se necesita un POST y una firma RS256, sin añadir
dependencias a un servicio pequeño.

## Radio taxis

`GET /api/ubicacion/radiotaxis` devuelve los disponibles con posición conocida; los clientes lo
consultan cada 30 s. Se prefirió el sondeo a WebSockets porque el Gateway actual no los enruta y una
latencia de 30 s es aceptable para este uso.

El sistema todavía **no tiene posiciones reales de vehículos**: el monolito solo guarda una parada
base por sindicato. La propiedad `origen` lo deja explícito en cada respuesta:

- `demo`: 4 radio taxis ficticios creados por el seeder si la tabla está vacía. El contenedor
  `simulador-radiotaxis` (`--profile demo`) los mueve a lo largo de las rutas.
- `monolito`: paradas base importadas con `php artisan ubicacion:migrar-radiotaxis`, que además retira
  los de demostración. Requiere la BD del monolito.

`PUT /api/ubicacion/radiotaxis/{id}/posicion` (solo admin) es el punto de entrada para actualizar
posiciones. Un dispositivo GPS en cada vehículo necesitaría un rol y credenciales propios: queda fuera
de este sprint.

## Revisión del equipo

La implementación y sus pruebas no equivalen a una aprobación de los integrantes.

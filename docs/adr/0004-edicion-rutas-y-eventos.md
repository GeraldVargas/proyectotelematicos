# ADR 0004 — Edición de rutas y publicación de eventos

- **Estado:** implementada localmente; pendiente de revisión del equipo.
- **Fecha:** 2026-10-27.

La edición `PUT /api/ubicacion/rutas/{id}` necesita conservar el cambio aunque Redis esté
temporalmente caído, sin perder el aviso destinado a los suscriptores. Una llamada directa
a Redis después de actualizar PostGIS dejaría esos dos efectos separados.

Se guarda la geometría y una fila en `eventos_salida` dentro de una transacción de PostGIS.
`worker-eventos-ubicacion` despacha los pendientes cada dos segundos a la lista compartida de
Redis. Ante error conserva el evento, aumenta `intentos` y reintenta. Una publicación repetida
mantiene el mismo UUID; el consumidor existente deduplica por `eventos_recibidos.evento_id`.

La respuesta HTTP confirma la persistencia y devuelve `meta.evento_id` y
`meta.notificacion: pendiente`. No confirma entrega al teléfono ni envío de push. El seeder
solo incorpora rutas ausentes y conserva los cambios del administrador en cada reinicio.

La API de Ubicación sigue sin puerto público. El Gateway verifica el JWT y sobrescribe los
headers de identidad. El middleware de edición exige `X-User-Id` numérico y rol `admin`.
La prueba de integración comprueba que un token de usuario no obtiene permisos enviando
headers falsos de administrador.

Limitaciones: el consumidor del ADR 0002 todavía usa BRPOP sin ACK. Un fallo del consumidor
entre sacar y persistir un evento puede perderlo. Esta mejora asegura el registro y reintento
del **productor**, sin declarar entrega garantizada de extremo a extremo. Redis Streams/ACK,
FCM y una cola de fallidos quedan pendientes para completar el sprint 2.

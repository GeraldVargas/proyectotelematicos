# ADR 0002 — Comunicación entre servicios: eventos en una cola Redis

- **Estado:** aceptada
- **Fecha:** 2026-10-14 (Sprint 1)
- **Responsable:** Backend — API de Notificaciones (acordado con Backend GIS)

## Contexto

Cuando cambia el trazado de una ruta, la API de Ubicación debe avisar a la API de Notificaciones
para que genere avisos a los usuarios suscritos a esa línea. Ubicación no debe depender de que
Notificaciones esté disponible en ese momento.

## Opciones

| Criterio | Webhook HTTP | RabbitMQ | Redis (lista) |
|---|---|---|---|
| Acoplamiento | Ubicación conoce la URL y espera la respuesta | Bajo | Bajo |
| Si Notificaciones está caído | Se pierde el aviso o hay que programar reintentos | El mensaje espera en la cola | El mensaje espera en la lista |
| Infraestructura nueva | Ninguna | Broker + panel + conceptos (exchanges, bindings) | Un contenedor pequeño, reutilizable como caché |
| Soporte en Laravel | `Http::post` | Paquete de terceros | Nativo (`Redis` facade, predis) |
| Entrega garantizada / ack | No | Sí | Parcial (BRPOP saca el mensaje antes de procesarlo) |

## Decisión

Para el Sprint 1 se usa **Redis 7.4** como broker, con una lista `colcatrufis:eventos`:

- Productor: `LPUSH` del evento JSON (`App\Services\PublicadorEventos` en Ubicación).
- Consumidor: `BRPOP` en el contenedor `worker-notificaciones` (`php artisan eventos:consumir`).
- Consumo **idempotente**: el `id` del evento se guarda en `eventos_recibidos`; si llega dos veces no
  duplica notificaciones.

Sobre común de todos los eventos (ejemplo completo en `docs/eventos/ruta.actualizada.json`):

```json
{
  "id": "uuid v4",
  "tipo": "ruta.actualizada",
  "version": 1,
  "origen": "api-ubicacion",
  "ocurrido_en": "ISO-8601",
  "datos": { "ruta_id": 2, "linea": "252", "sindicato": "…", "cambio": "trazado", "descripcion": "…" }
}
```

`datos.cambio` puede ser `trazado`, `horario`, `tarifa` o `estado`. Un cambio incompatible del
formato sube `version`.

## Consecuencias

- (+) Mínima infraestructura y sin sobre-ingeniería para la prueba de concepto.
- (−) Si el worker falla entre `BRPOP` y el guardado, el evento se pierde. Si hace falta entrega
  garantizada, migrar a Redis Streams (`XREADGROUP` + `XACK`) sin cambiar el formato del evento.
- Fuera del Sprint 1: notificaciones push al móvil (FCM) y cola de mensajes fallidos.

# Sprint 2 — avance del 27/10/2026

El administrador puede editar visualmente un trazado en el panel y guardar el motivo del cambio.
Ubicación guarda la geometría y su evento en una transacción. Un nuevo worker reintenta enviarlo
a Redis; Notificaciones genera avisos para los suscriptores. También se puede elegir un punto
del mapa y consultar sus paradas cercanas.

| Implementado | Alcance |
|---|---|
| GET `/api/ubicacion/paradas/cercanas?lat=&lng=&radio=&limite=` | ST_DWithin sobre geography, GiST, distancia en metros; radio 1–5000, límite 1–100 |
| PUT `/api/ubicacion/rutas/{id}` | Solo admin; LineString validado, atributos y motivo |
| Publicación persistida | `eventos_salida`, UUID estable y reintentos desde `worker-eventos-ubicacion` |
| Persistencia de ediciones | El seeder agrega rutas ausentes sin sobrescribir cambios existentes |
| Panel | Arrastrar/añadir/quitar/restablecer puntos, cancelar/guardar y consultar cercanía |
| Swagger | Ubicación 1.1.0: permisos, solicitudes y respuestas nuevas |
| Pruebas | 11 casos GIS/outbox nuevos, 40 comprobaciones integradas y guardado en Chrome |
| CI ampliado | Cobertura PHP, Flutter, build web, smoke Docker, Chrome y k6 parcial |

Evidencia: [captura de guardado](qa/artifacts/panel-ruta-guardada.png).
Decisión y límites de entrega: [ADR 0004](adr/0004-edicion-rutas-y-eventos.md).

```powershell
docker compose up --build
```

Abre http://localhost:8081 como `admin@colcatrufis.local` / `admin12345` y elige **Editar trazado**.
El rol usuario recibe 403 al editar, incluso si intenta enviar headers de administrador.
Los headers de identidad los establece KrakenD a partir del JWT.

## Pendiente del resto del sprint 2

- FCM/push, registro de dispositivos y recepción en Android.
- Historial filtrado, desuscripción y pantalla móvil de suscripciones.
- Radio taxis reales, capas web/móvil y actualización periódica.
- Logout con revocación, lista de usuarios y permisos de consulta de otros perfiles.
- Más rutas, revisión municipal y migración cuando esté disponible la BD histórica.
- Verificaciones y aprobación formal del equipo; ejecución remota del CI.

El sprint 2 no se declara terminado. Los avisos implementados son registros en la API,
no notificaciones push al teléfono.

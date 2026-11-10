# Checklist final — Sprint 3

Esta checklist corresponde a la tarea «Ejecutar la checklist de pruebas manuales final (flujo
completo del sistema) y documentarla en `docs/qa-sprint-3.md`». Debe firmarla la persona que
realice las pruebas; no se marca como terminada solo por existir este archivo.

| # | Prueba manual | Estado actual | Evidencia o condición |
|---:|---|---|---|
| 1 | Login Flutter → Gateway → API de Usuarios | Pendiente | Requiere Android físico y Docker. |
| 2 | Mapa Flutter con rutas y paradas PostGIS | Pendiente | Requiere Android físico y Docker. |
| 3 | Administrador edita ruta en panel y observa el cambio | Pendiente de repetición | Existía evidencia Chrome; requiere Docker para repetirla. |
| 4 | Evento `ruta.actualizada` llega a Notificaciones | Pendiente de repetición | Probado previamente hasta FCM simulado; requiere Docker. |
| 5 | Push llega al teléfono suscrito | Pendiente | Requiere Firebase real y teléfono. |
| 6 | Swagger muestra y ejecuta endpoints de las tres APIs | Pendiente de apertura manual | Los 21 endpoints Gateway/OpenAPI se validaron estáticamente. |
| 7 | Radio taxis se actualizan en panel y móvil | Pendiente de repetición | Requiere perfil demo de Docker; móvil requiere teléfono. |
| 8 | Sin bugs críticos durante el flujo | Pendiente de firma | Se completa después de los pasos 1–7. |

Resultado final: **pendiente de ejecución manual y firma**.

Firma: _______________________________  Fecha: __________________

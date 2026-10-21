# ADR 0001 — API Gateway: KrakenD

- **Estado:** aceptada
- **Fecha:** 2026-10-14 (Sprint 1)
- **Responsable:** DevOps / API Gateway

## Contexto

La arquitectura objetivo pone un API Gateway como único punto de entrada para la app Flutter y el
panel web. El Gateway debe validar JWT RS256 de forma centralizada y enrutar `/api/{servicio}/...`
hacia tres microservicios Laravel. El análisis se limitó a 2 días (riesgo de curva de aprendizaje).

## Opciones comparadas

| Criterio | Kong (OSS, modo DB-less) | KrakenD (CE) |
|---|---|---|
| Configuración | YAML declarativo; el plugin `jwt` exige registrar *consumers* con `iss` = key | Un archivo JSON (con plantillas) sin estado |
| Validación JWT RS256 | Plugin `jwt` con la clave pública por consumer | `auth/validator` nativo con JWK local o remoto |
| Pasar claims al servicio | Requiere plugin adicional o Lua | `propagate_claims` (sub → `X-User-Id`) incluido |
| Dependencias | Postgres opcional; imagen ~150 MB | Ninguna; binario Go ~80 MB |
| Rutas con comodín | Sí (`/api/usuarios`, prefijo) | No en CE: endpoints explícitos |
| Rendimiento | Bueno (Nginx/OpenResty) | Muy bueno, sin estado |
| Curva para el equipo | Media (consumers, plugins, Admin API) | Baja (un JSON) |

## Decisión

Se usa **KrakenD 2.7.2 CE** con *Flexible Configuration*:

- `gateway/krakend.tmpl` define la plantilla común (CORS, logging, validación JWT, reenvío `no-op`).
- `gateway/settings/endpoints.json` lista cada endpoint, su servicio destino y si es público.
- `POST /api/usuarios/login`, `/registro` y `/refresh` son públicos; todo lo demás exige
  `Authorization: Bearer <JWT>`.
- La clave pública llega como `jwk.json` por el volumen Docker `jwt-keys`, que escribe la API de
  Usuarios al arrancar. El `kid` del header del token debe coincidir (`colcatrufis-1`).
- Los claims `sub` y `rol` se reenvían como `X-User-Id` y `X-User-Rol`. Los servicios no exponen
  puertos fuera de la red de Docker.

## Consecuencias

- (+) Configuración versionada y revisable en PR; sin base de datos del Gateway.
- (+) La lista explícita de endpoints coincide con los contratos OpenAPI (contract-first).
- (−) Cada endpoint nuevo se agrega a `endpoints.json` (no hay comodines en CE).
- (−) Rotar la clave requiere reiniciar el Gateway (`docker compose restart gateway`).
- Pendiente: limitar por rol (`roles_key: rol`) cuando existan endpoints solo-admin (PUT rutas).

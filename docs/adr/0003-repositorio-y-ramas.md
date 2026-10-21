# ADR 0003 — Monorepo y estrategia de ramas

- **Estado:** aceptada
- **Fecha:** 2026-10-13 (Sprint 1)

## Decisión

**Monorepo** (`proyectotelematicos`): el monolito (`backend/`), la app (`fronted/`), los
microservicios (`services/`), el Gateway (`gateway/`), el panel (`admin-web/`) y las pruebas de
rendimiento (`perf/`) viven juntos. Con 6 integrantes y contratos que cambian a la vez en varios
servicios, un solo PR puede tocar contrato + servicio + cliente y el CI lo valida completo.

Ramas (GitHub Flow simplificado):

- `main`: siempre levanta con `docker compose up`. Protegida; solo entra por PR con CI en verde.
- `feat/<servicio>-<tema>` (p. ej. `feat/ubicacion-geojson`), `fix/...`, `docs/...`.
- PR pequeño, al menos 1 revisión de otro integrante.

## CI (`.github/workflows/ci.yml`)

En cada PR y push a `main`: tests PHPUnit de los 3 servicios (Ubicación contra un PostGIS de
servicio), `pint --test` como lint, validación de los contratos OpenAPI, de la configuración del
Gateway y `docker compose config`.

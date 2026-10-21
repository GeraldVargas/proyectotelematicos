# API Gateway (KrakenD)

Punto de entrada único: `http://localhost:8080`. Decisión en [docs/adr/0001-api-gateway.md](../docs/adr/0001-api-gateway.md).

- `krakend.tmpl` — plantilla de la configuración (Flexible Configuration de KrakenD).
- `settings/endpoints.json` — **aquí se agregan las rutas.** Cada entrada indica el servicio destino y si es pública.

Todas las rutas no públicas exigen `Authorization: Bearer <JWT RS256>`. El Gateway valida firma y `exp`
con `/keys/jwk.json` (lo genera la API de Usuarios en el volumen compartido `jwt-keys`) y reenvía al
servicio los claims `sub` → `X-User-Id` y `rol` → `X-User-Rol`.

KrakenD CE no admite comodines (`/api/usuarios/*`): cada endpoint se declara explícitamente, lo que
además mantiene el Gateway alineado con los contratos OpenAPI de `docs/openapi/`.

Para ver la configuración final generada:

```bash
docker compose run --rm gateway check -c /etc/krakend/krakend.tmpl -dtc
```

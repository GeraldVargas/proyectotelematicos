// URL del API Gateway. En local, docker compose lo publica en el puerto 8080.
window.COLCATRUFIS_CONFIG = {
  gatewayUrl: 'http://localhost:8080',
  // Mapa base vectorial: va directo al proveedor, sin pasar por el Gateway (datos públicos)
  estiloMapaBase: 'https://tiles.openfreemap.org/styles/liberty',
  centro: [-66.2405, -17.3895], // Colcapirhua [lng, lat]
  zoom: 13,
};

import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';

// Fixtures sin datos personales, reproducibles a partir de la misma semilla OSM del backend.
const root = new URL('../', import.meta.url);
const seed = JSON.parse(readFileSync(new URL('services/api-ubicacion/database/data/rutas_colcapirhua.geojson', root), 'utf8'));
const stops = new Map();
const routes = seed.features.map((feature, index) => {
  const { paradas, osm_relation_id, ...properties } = feature.properties;
  const id = index + 1;
  for (const stop of paradas) {
    const key = stop.osm_id ?? stop.coordinates.join(',');
    if (!stops.has(key)) {
      const stopId = stops.size + 1;
      stops.set(key, {
        type: 'Feature', id: stopId,
        geometry: { type: 'Point', coordinates: stop.coordinates },
        properties: { id: stopId, nombre: stop.nombre, descripcion: null, rutas: [] },
      });
    }
    const memberships = stops.get(key).properties.rutas;
    if (!memberships.some((route) => route.id === id)) {
      memberships.push({ id, linea: properties.linea, color: properties.color });
    }
  }
  return { type: 'Feature', id, geometry: feature.geometry, properties: {
    id, ...properties, estado: 'activa', tarifa: null, horario: null, frecuencia_min: null,
  } };
});
const output = new URL('fronted/assets/mocks/', root);
mkdirSync(output, { recursive: true });
for (const [name, features] of [['ubicacion_rutas', routes], ['ubicacion_paradas', [...stops.values()]]]) {
  writeFileSync(new URL(`${name}.json`, output), `${JSON.stringify({ type: 'FeatureCollection', features }, null, 2)}\n`);
}
console.log(`Fixtures: ${routes.length} rutas y ${stops.size} paradas (fuente: semilla OpenStreetMap).`);

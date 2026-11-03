// Amplía database/data/rutas_colcapirhua.geojson con líneas reales de OpenStreetMap.
// Uso: node scripts/importar-rutas-osm.mjs        (requiere Internet; no modifica las rutas ya presentes)
//
// Criterio (una relación por línea, sentido con más paradas):
//   - relación de transporte que pasa por el municipio de Colcapirhua, con operador conocido
//   - al menos MIN_KM recorridos dentro del recuadro aproximado del municipio
//   - trazado continuo (sin saltos de más de MAX_SALTO_M) y al menos MIN_PARADAS paradas
import { readFileSync, writeFileSync } from 'node:fs';

const MIN_KM = 5;
const MIN_PARADAS = 3;
const MAX_SALTO_M = 50;
const RECUADRO = { oeste: -66.285, sur: -17.425, este: -66.205, norte: -17.36 };
const OVERPASS = ['https://overpass-api.de/api/interpreter', 'https://overpass.private.coffee/api/interpreter', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter'];
const COLORES = ['#8E24AA', '#00897B', '#F4511E', '#3949AB', '#7CB342', '#D81B60', '#00ACC1', '#6D4C41', '#5E35B1', '#C0CA33',
  '#039BE5', '#EF6C00', '#2E7D32', '#AD1457', '#546E7A', '#FFB300', '#1565C0', '#9E9D24', '#BF360C', '#00695C'];
const destino = new URL('../services/api-ubicacion/database/data/rutas_colcapirhua.geojson', import.meta.url);

// Los servidores públicos de Overpass se saturan seguido: se reintenta y se prueba otro.
async function overpass(consulta) {
  let ultimoError;
  for (let intento = 0; intento < 3 * OVERPASS.length; intento++) {
    try {
      const res = await fetch(OVERPASS[intento % OVERPASS.length], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'ColcaTrufis-proyecto-universitario' },
        body: new URLSearchParams({ data: consulta }),
      });
      if (!res.ok) throw new Error(`Overpass respondió ${res.status}`);
      return (await res.json()).elements;
    } catch (error) {
      ultimoError = error;
      await new Promise((listo) => setTimeout(listo, 5000));
    }
  }
  throw ultimoError;
}

const metros = ([lngA, latA], [lngB, latB]) => Math.hypot((lngA - lngB) * 106000, (latA - latB) * 111000);
const dentro = ([lng, lat]) => lng > RECUADRO.oeste && lng < RECUADRO.este && lat > RECUADRO.sur && lat < RECUADRO.norte;

// Encadena las vías en orden, invirtiendo las que están al revés. Devuelve null si hay un salto.
function trazado(relacion) {
  const vias = relacion.members
    .filter((m) => m.type === 'way' && m.geometry && ['', 'forward', 'backward'].includes(m.role))
    .map((m) => m.geometry.map((p) => [p.lon, p.lat]));
  if (vias.length === 0) return null;
  if (vias.length > 1 && Math.min(metros(vias[0][0], vias[1][0]), metros(vias[0][0], vias[1].at(-1))) < Math.min(metros(vias[0].at(-1), vias[1][0]), metros(vias[0].at(-1), vias[1].at(-1)))) {
    vias[0].reverse();
  }
  const linea = [...vias[0]];
  for (const via of vias.slice(1)) {
    const fin = linea.at(-1);
    const orientada = metros(fin, via.at(-1)) < metros(fin, via[0]) ? [...via].reverse() : via;
    if (metros(fin, orientada[0]) > MAX_SALTO_M) return null;
    linea.push(...(metros(fin, orientada[0]) === 0 ? orientada.slice(1) : orientada));
  }
  return linea;
}

const coleccion = JSON.parse(readFileSync(destino, 'utf8'));
const existentes = new Set(coleccion.features.map((f) => f.properties.linea));

const relaciones = await overpass('[out:json][timeout:170];area["name"="Colcapirhua"]->.a;relation["type"="route"]["route"~"^(bus|share_taxi|minibus)$"](area.a);out geom;');
const candidatas = new Map();
for (const r of relaciones) {
  const { ref, operator } = r.tags;
  if (!ref || !operator || existentes.has(ref)) continue;
  const linea = trazado(r);
  if (!linea) continue;
  let km = 0;
  for (let i = 1; i < linea.length; i++) if (dentro(linea[i - 1])) km += metros(linea[i - 1], linea[i]) / 1000;
  const paradas = r.members.filter((m) => m.type === 'node' && /^(stop|platform)/.test(m.role) && m.lat !== undefined);
  if (km < MIN_KM || new Set(paradas.map((p) => p.ref)).size < MIN_PARADAS) continue;
  const actual = candidatas.get(ref);
  if (!actual || paradas.length > actual.paradas.length) candidatas.set(ref, { relacion: r, linea, paradas });
}

const elegidas = [...candidatas.values()].sort((a, b) => a.relacion.tags.ref.localeCompare(b.relacion.tags.ref, 'es', { numeric: true }));
const ids = [...new Set(elegidas.flatMap((e) => e.paradas.map((p) => p.ref)))];
const nombres = new Map((await overpass(`[out:json][timeout:120];node(id:${ids.join(',')});out tags;`)).map((n) => [n.id, n.tags?.name]));

elegidas.forEach(({ relacion, linea, paradas }, i) => {
  const t = relacion.tags;
  const vistas = new Set();
  coleccion.features.push({
    type: 'Feature',
    geometry: { type: 'LineString', coordinates: linea },
    properties: {
      osm_relation_id: relacion.id, linea: t.ref, nombre: t.name, sindicato: t.operator,
      desde: t.from ?? null, hasta: t.to ?? null, tipo: t.name?.split(' ')[0] ?? null,
      color: COLORES[i % COLORES.length],
      paradas: paradas.filter((p) => !vistas.has(p.ref) && vistas.add(p.ref)).map((p) => ({
        osm_id: p.ref, coordinates: [p.lon ?? p.geometry?.lon, p.lat ?? p.geometry?.lat], nombre: nombres.get(p.ref) || 'Parada sin nombre',
      })),
    },
  });
});

writeFileSync(destino, `${JSON.stringify(coleccion)}\n`);
console.log(`${elegidas.length} líneas agregadas (${elegidas.map((e) => e.relacion.tags.ref).join(', ')}); total ${coleccion.features.length}.`);

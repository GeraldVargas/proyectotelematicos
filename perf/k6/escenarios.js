// Escenarios de carga ColcaTrufis — línea base del monolito (Sprint 1) y comparación
// con microservicios (Sprint 2). Los MISMOS escenarios, VUs y duración se usan en ambos.
//
//   TARGET=monolito        BASE_URL=http://host.docker.internal:8000
//   TARGET=microservicios  BASE_URL=http://host.docker.internal:8080   (API Gateway)
//
// Ver perf/README.md para las condiciones de la prueba.
import http from 'k6/http';
import { check, group, fail } from 'k6';
import { Trend } from 'k6/metrics';

const TARGET = __ENV.TARGET || 'monolito';
const BASE_URL = (__ENV.BASE_URL || 'http://host.docker.internal:8000').replace(/\/$/, '');
const EMAIL = __ENV.EMAIL || 'admin@colcatrufis.local';
const PASSWORD = __ENV.PASSWORD || 'admin12345';
const VUS = Number(__ENV.VUS || 20);
const DURACION = __ENV.DURACION || '2m';

const latencia = {
  login: new Trend('latencia_login', true),
  rutas: new Trend('latencia_rutas', true),
  radiotaxis: new Trend('latencia_radiotaxis', true),
};

// Endpoints equivalentes en cada arquitectura
const RUTAS = {
  monolito: { rutas: '/api/trufis/rutas/geojson', radiotaxis: '/api/radiotaxis/paradas/geojson' },
  microservicios: { rutas: '/api/ubicacion/rutas', radiotaxis: '/api/ubicacion/radiotaxis' },
}[TARGET];

if (!RUTAS) fail(`TARGET desconocido: ${TARGET}`);

const escenario = (exec, vus) => ({
  executor: 'ramping-vus',
  exec,
  startVUs: 0,
  stages: [
    { duration: '20s', target: vus }, // rampa
    { duration: DURACION, target: vus }, // carga sostenida (lo que se reporta)
    { duration: '10s', target: 0 },
  ],
  gracefulRampDown: '5s',
});

// Mezcla de tráfico: la mayoría consulta rutas; el login es menos frecuente.
const escenarios = {
  login: escenario('login', Math.max(1, Math.round(VUS * 0.2))),
  rutas: escenario('rutas', Math.max(1, Math.round(VUS * 0.6))),
};
// En microservicios radiotaxis llega en el Sprint 2; se puede excluir con SIN_RADIOTAXIS=1
if (!__ENV.SIN_RADIOTAXIS) {
  escenarios.radiotaxis = escenario('radiotaxis', Math.max(1, Math.round(VUS * 0.2)));
}

export const options = {
  scenarios: escenarios,
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  thresholds: {
    // RNF5: consultas de rutas < 2 s
    'latencia_rutas': ['p(95)<2000'],
    'http_req_failed': ['rate<0.01'],
  },
};

let token = null; // microservicios: un JWT por VU

function cabeceras() {
  const h = { Accept: 'application/json' };
  if (TARGET === 'microservicios') {
    if (!token) token = loginGateway();
    h.Authorization = `Bearer ${token}`;
  }
  return h;
}

function loginGateway() {
  const res = http.post(`${BASE_URL}/api/usuarios/login`, JSON.stringify({ email: EMAIL, password: PASSWORD }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { name: 'login' },
  });
  latencia.login.add(res.timings.duration);
  check(res, { 'login 200': (r) => r.status === 200 });
  return res.status === 200 ? res.json('access_token') : null;
}

function loginMonolito() {
  // Panel Blade: formulario con token CSRF + cookie de sesión
  const jar = http.cookieJar();
  jar.clear(BASE_URL);
  const form = http.get(`${BASE_URL}/admin/login`, { tags: { name: 'login_form' } });
  const csrf = form.html().find('input[name=_token]').attr('value');
  const res = http.post(`${BASE_URL}/admin/login`, { _token: csrf, email: EMAIL, password: PASSWORD }, {
    redirects: 0,
    tags: { name: 'login' },
  });
  latencia.login.add(res.timings.duration);
  check(res, {
    'login redirige al dashboard': (r) => r.status === 302 && String(r.headers.Location).includes('/admin/dashboard'),
  });
}

export function login() {
  group('login', () => (TARGET === 'monolito' ? loginMonolito() : loginGateway()));
}

export function rutas() {
  group('rutas', () => {
    const res = http.get(`${BASE_URL}${RUTAS.rutas}`, { headers: cabeceras(), tags: { name: 'rutas' } });
    latencia.rutas.add(res.timings.duration);
    check(res, {
      'rutas 200': (r) => r.status === 200,
      'rutas es FeatureCollection': (r) => r.status === 200 && r.json('type') === 'FeatureCollection',
    });
  });
}

export function radiotaxis() {
  group('radiotaxis', () => {
    const res = http.get(`${BASE_URL}${RUTAS.radiotaxis}`, { headers: cabeceras(), tags: { name: 'radiotaxis' } });
    latencia.radiotaxis.add(res.timings.duration);
    check(res, { 'radiotaxis 200': (r) => r.status === 200 });
  });
}

export function handleSummary(data) {
  const fecha = new Date().toISOString().replace(/[:.]/g, '-');
  const archivo = `/resultados/${TARGET}-${fecha}.json`;
  const m = data.metrics;
  const fila = (nombre) => {
    const v = m[`latencia_${nombre}`]?.values;
    return v ? `| ${nombre} | ${v.med.toFixed(0)} | ${v['p(95)'].toFixed(0)} | ${v.count ?? '-'} |` : `| ${nombre} | - | - | - |`;
  };
  const resumen = [
    `# ${TARGET} — ${new Date().toISOString()}`,
    `BASE_URL=${BASE_URL} VUS=${VUS} DURACION=${DURACION}`,
    '',
    '| Endpoint | p50 (ms) | p95 (ms) | muestras |',
    '|---|---|---|---|',
    fila('login'),
    fila('rutas'),
    fila('radiotaxis'),
    '',
    `Throughput: ${m.http_reqs.values.rate.toFixed(1)} req/s — Tasa de error: ${(m.http_req_failed.values.rate * 100).toFixed(2)} %`,
    '',
  ].join('\n');
  return {
    stdout: resumen,
    [archivo]: JSON.stringify(data, null, 2),
    [archivo.replace('.json', '.md')]: resumen,
  };
}

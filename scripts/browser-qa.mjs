// Prueba real de navegador sin paquetes npm. Requiere Node >=22 y Chrome/Chromium.
import { spawn } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const panel = process.env.PANEL_URL || 'http://localhost:8081';
const chrome = process.env.CHROME_PATH || [
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
].find(existsSync);
if (!chrome) throw new Error('No se encontró Chrome. Define CHROME_PATH.');
const artifacts = fileURLToPath(new URL('../docs/qa/artifacts/', import.meta.url));
mkdirSync(artifacts, { recursive: true });
const temporaryRoot = path.resolve(os.tmpdir());
const profile = mkdtempSync(path.join(temporaryRoot, 'colcatrufis-qa-'));
const report = { fecha: new Date().toISOString(), panel, checks: [], status: 'running' };
const browser = spawn(chrome, [
  '--headless=new', '--no-first-run', '--no-default-browser-check', '--window-size=1440,1000',
  '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
  ...(process.env.CI ? ['--no-sandbox', '--disable-dev-shm-usage'] : []),
  `--user-data-dir=${profile}`, '--remote-debugging-port=0', 'about:blank',
], { stdio: 'ignore', windowsHide: true });
browser.on('error', (error) => { report.spawnError = error.message; });
let socket, sequence = 0;
const pending = new Map();
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
function check(condition, message) {
  if (!condition) throw new Error(message);
  report.checks.push(message);
  console.log(`OK: ${message}`);
}
async function until(action, label, timeout = 45000) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try { const result = await action(); if (result) return result; } catch { /* navegación o carga en curso */ }
    await pause(200);
  }
  throw new Error(`Tiempo agotado: ${label}`);
}
function send(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`CDP: ${method}`)); }, 20000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
  return result.result?.value;
}
async function screenshot(name) {
  const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  writeFileSync(path.join(artifacts, name), Buffer.from(data, 'base64'));
}
async function login(email, password) {
  await evaluate(`(() => {
    const form = document.getElementById('form-login');
    form.elements.email.value = ${JSON.stringify(email)};
    form.elements.password.value = ${JSON.stringify(password)};
    form.requestSubmit();
  })()`);
}
async function swaggerGet(contract, endpoint, expected, capture) {
  await evaluate(`(() => {
    const select = document.querySelector('.topbar select');
    select.value = [...select.options].find(o => o.value.endsWith(${JSON.stringify(contract + '.yaml')})).value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
  await until(() => evaluate(`!![...document.querySelectorAll('.opblock')].find(b => b.querySelector('.opblock-summary-path')?.textContent.trim() === ${JSON.stringify(endpoint)} && b.querySelector('.opblock-summary-method')?.textContent === 'GET')`), `contrato ${contract}: ${endpoint}`);
  await evaluate(`(() => {
    ui.preauthorizeApiKey('bearerAuth', window.__qaAdminToken);
    window.__qaOperation = () => [...document.querySelectorAll('.opblock')].find(b => b.querySelector('.opblock-summary-path')?.textContent.trim() === ${JSON.stringify(endpoint)} && b.querySelector('.opblock-summary-method')?.textContent === 'GET');
    __qaOperation().querySelector('.opblock-summary-control').click();
  })()`);
  await until(() => evaluate(`!!__qaOperation()?.querySelector('.try-out__btn')`), 'Try it out');
  await evaluate(`__qaOperation().querySelector('.try-out__btn').click()`);
  await until(() => evaluate(`!!__qaOperation().querySelector('.execute')`), 'botón Execute');
  await evaluate(`__qaOperation().querySelector('.execute').click()`);
  await until(() => evaluate(`[...__qaOperation().querySelectorAll('.live-responses-table .response-col_status')].some(c => c.textContent.trim().startsWith('200'))`), `respuesta Swagger 200: ${endpoint}`);
  check(await evaluate(`__qaOperation().querySelector('.live-responses-table').textContent.includes(${JSON.stringify(expected)})`), `Swagger Try it out ejecuta ${endpoint} con JWT y devuelve ${expected}`);
  await screenshot(capture);
}
try {
  const portFile = path.join(profile, 'DevToolsActivePort');
  const port = await until(() => existsSync(portFile) && readFileSync(portFile, 'utf8').split('\n')[0], 'inicio de Chrome', 20000);
  const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  const page = pages.find((p) => p.type === 'page');
  socket = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  socket.onmessage = ({ data }) => {
    const message = JSON.parse(data);
    const waiting = pending.get(message.id);
    if (!waiting) return;
    clearTimeout(waiting.timer);
    pending.delete(message.id);
    message.error ? waiting.reject(new Error(message.error.message)) : waiting.resolve(message.result);
  };
  await send('Page.enable');
  await send('Runtime.enable');
  // Observación exclusiva del test: no añade variables ni rutas de depuración al producto.
  await send('Page.addScriptToEvaluateOnNewDocument', { source: `
    window.addEventListener('DOMContentLoaded', () => {
      if (!window.maplibregl) return;
      const Original = maplibregl.Map;
      maplibregl.Map = class extends Original { constructor(options) { super(options); window.__qaMap = this; } };
    });` });
  await send('Page.navigate', { url: panel });
  await until(() => evaluate(`document.readyState === 'complete' && !!document.getElementById('form-login') && !!window.maplibregl`), 'panel y MapLibre');
  await login('admin@colcatrufis.local', 'admin12345');
  await until(() => evaluate(`!document.getElementById('vista-mapa').hidden && document.getElementById('estado').textContent.includes('paradas desde PostGIS')`), 'login y rutas reales');
  check(await evaluate(`document.querySelectorAll('#lista-rutas > li').length >= 4`), 'Administrador ve mapa y al menos cuatro rutas');
  await screenshot('panel-mapa.png');
  await evaluate(`document.querySelector('#lista-rutas input').click()`);
  check(await evaluate(`__qaMap.getFilter('rutas-borde')[2][1].length === document.querySelectorAll('#lista-rutas > li').length - 1`), 'Desmarcar una ruta actualiza el filtro del mapa');
  await evaluate(`document.querySelector('#lista-rutas input').click(); document.getElementById('toggle-paradas').click()`);
  check(await evaluate(`__qaMap.getLayoutProperty('paradas', 'visibility') === 'none'`), 'Ocultar paradas cambia la capa del mapa');
  await evaluate(`document.getElementById('toggle-paradas').click()`);
  await until(() => evaluate(`__qaMap.loaded()`), 'mapa renderizado');
  await evaluate(`(() => { const c = __qaMap.getSource('rutas')._data.features[0].geometry.coordinates; const p = c[Math.floor(c.length/2)]; __qaMap.fire('click', { point: __qaMap.project(p), lngLat: new maplibregl.LngLat(...p) }); })()`);
  check(await evaluate(`[...document.querySelectorAll('.maplibregl-popup-content')].some(p => p.textContent.includes('Línea'))`), 'Seleccionar ruta muestra línea y sindicato');
  await evaluate(`(() => { const p = __qaMap.getSource('paradas')._data.features[0].geometry.coordinates; __qaMap.fire('click', { point: __qaMap.project(p), lngLat: new maplibregl.LngLat(...p) }); })()`);
  check(await evaluate(`[...document.querySelectorAll('.maplibregl-popup-content')].some(p => p.textContent.includes('Líneas:'))`), 'Seleccionar parada muestra sus líneas');
  await screenshot('panel-detalle.png');
  await evaluate(`document.querySelector('.ruta-editar').click()`);
  check(await evaluate(`!document.getElementById('editor-ruta').hidden && !!__qaMap.getLayer('edicion-puntos')`), 'Editor administrativo muestra puntos del trazado');
  await until(() => evaluate(`__qaMap.loaded() && __qaMap.queryRenderedFeatures({ layers: ['edicion-puntos'] }).length > 0`), 'puntos del editor renderizados');
  const vertex = await evaluate(`(() => {
    const coordinates = __qaMap.getSource('edicion')._data.features[0].geometry.coordinates;
    window.__qaOriginal = JSON.stringify(coordinates);
    const p = __qaMap.project(coordinates[Math.floor(coordinates.length / 2)]); const bounds = __qaMap.getCanvas().getBoundingClientRect();
    return {x: p.x + bounds.left, y: p.y + bounds.top, count: coordinates.length};
  })()`);
  await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: vertex.x, y: vertex.y, button: 'left', clickCount: 1 });
  await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: vertex.x + 12, y: vertex.y + 12, button: 'left', buttons: 1 });
  await pause(100);
  await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: vertex.x + 12, y: vertex.y + 12, button: 'left', clickCount: 1 });
  check(await evaluate(`JSON.stringify(__qaMap.getSource('edicion')._data.features[0].geometry.coordinates) !== __qaOriginal`), 'Arrastrar un punto cambia el trazado temporal');
  await evaluate(`document.getElementById('editor-quitar').click()`);
  check(await evaluate(`__qaMap.getSource('edicion')._data.features[0].geometry.coordinates.length === ${vertex.count - 1}`), 'Quitar punto elimina el vértice seleccionado');
  await evaluate(`document.getElementById('editor-restablecer').click()`);
  check(await evaluate(`JSON.stringify(__qaMap.getSource('edicion')._data.features[0].geometry.coordinates) === __qaOriginal`), 'Restablecer recupera el trazado original');
  await evaluate(`document.getElementById('editor-cancelar').click()`);
  check(await evaluate(`document.getElementById('editor-ruta').hidden && !__qaMap.getSource('edicion')`), 'Cancelar edición retira el trazado temporal');
  await evaluate(`document.querySelector('.ruta-editar').click(); document.querySelector('#form-ruta textarea').value = 'Verificación del editor web: se conserva el trazado actual.'; document.getElementById('form-ruta').requestSubmit()`);
  await until(() => evaluate(`document.getElementById('editor-ruta').hidden && document.getElementById('estado').textContent.includes('Ruta guardada')`), 'guardar trazado desde el panel');
  check(true, 'Guardar desde el editor web confirma la actualización del trazado');
  await screenshot('panel-ruta-guardada.png');
  await evaluate(`document.getElementById('btn-cercanas').click(); (() => { const p = __qaMap.getSource('paradas')._data.features[0].geometry.coordinates; __qaMap.fire('click', { point: __qaMap.project(p), lngLat: new maplibregl.LngLat(...p) }); })()`);
  await until(() => evaluate(`document.querySelectorAll('#lista-cercanas li').length > 0`), 'consulta de cercanía');
  check(true, 'Panel muestra paradas cercanas obtenidas del Gateway');
  await until(() => evaluate(`__qaMap.getSource('radiotaxis')?._data?.features?.length > 0`), 'capa de radio taxis');
  check(await evaluate(`!!__qaMap.getLayer('radiotaxis') && /\\(\\d+\\)/.test(document.getElementById('radiotaxis-total').textContent)`), 'Panel muestra la capa de radio taxis desde la API');
  await until(() => evaluate(`__qaMap.loaded()`), 'radio taxis renderizados');
  await evaluate(`(() => { const p = __qaMap.getSource('radiotaxis')._data.features[0].geometry.coordinates; __qaMap.jumpTo({ center: p, zoom: 16 }); })()`);
  await until(() => evaluate(`__qaMap.loaded()`), 'zoom al radio taxi');
  await evaluate(`(() => { const p = __qaMap.getSource('radiotaxis')._data.features[0].geometry.coordinates; __qaMap.fire('click', { point: __qaMap.project(p), lngLat: new maplibregl.LngLat(...p) }); })()`);
  await until(() => evaluate(`[...document.querySelectorAll('.maplibregl-popup-content')].some(p => p.textContent.includes('Radio Taxi'))`), 'detalle de radio taxi');
  check(true, 'Seleccionar un radio taxi muestra su detalle');
  await screenshot('panel-radiotaxis.png');
  await evaluate(`document.getElementById('toggle-radiotaxis').click()`);
  check(await evaluate(`__qaMap.getLayoutProperty('radiotaxis', 'visibility') === 'none'`), 'Ocultar radio taxis cambia la capa del mapa');
  await evaluate(`document.getElementById('toggle-radiotaxis').click(); document.getElementById('btn-usuarios').click()`);
  await until(() => evaluate(`document.querySelectorAll('#lista-usuarios li').length >= 2`), 'lista de usuarios');
  check(await evaluate(`document.getElementById('lista-usuarios').textContent.includes('admin@colcatrufis.local')`), 'Administrador ve la lista de usuarios');
  await screenshot('panel-usuarios.png');
  const refreshAntes = await evaluate(`JSON.parse(sessionStorage.getItem('colcatrufis.sesion')).refresh_token`);
  await evaluate(`document.getElementById('btn-salir').click()`);
  await until(() => evaluate(`!document.getElementById('vista-login').hidden`), 'cierre de sesión');
  const refreshRevocado = await evaluate(`fetch(${JSON.stringify((process.env.GATEWAY_URL || 'http://localhost:8080') + '/api/usuarios/refresh')}, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({refresh_token: ${JSON.stringify(refreshAntes)}}) }).then(r => r.status)`);
  check(refreshRevocado === 401, 'Salir del panel revoca el refresh token en el servidor');
  await login('demo@colcatrufis.local', 'demo12345');
  await until(() => evaluate(`document.getElementById('login-error').textContent.includes('no tiene acceso')`), 'rechazo de rol usuario');
  check(await evaluate(`document.getElementById('vista-mapa').hidden && !sessionStorage.getItem('colcatrufis.sesion')`), 'El rol usuario no entra al panel ni conserva sesión administrativa');
  await screenshot('panel-rol-rechazado.png');
  await send('Page.navigate', { url: process.env.SWAGGER_URL || 'http://localhost:8090' });
  await until(() => evaluate(`document.querySelector('.topbar select')?.options.length === 3 && !!window.ui`), 'Swagger UI con tres contratos');
  check(true, 'Swagger UI muestra los tres servicios');
  const gateway = process.env.GATEWAY_URL || 'http://localhost:8080';
  await evaluate(`(async () => {
    const response = await fetch(${JSON.stringify(gateway + '/api/usuarios/login')}, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({email:'admin@colcatrufis.local',password:'admin12345'}) });
    const session = await response.json(); window.__qaAdminToken = session.access_token;
  })()`);
  await swaggerGet('ubicacion', '/api/ubicacion/rutas', 'FeatureCollection', 'swagger-rutas.png');
  await swaggerGet('ubicacion', '/api/ubicacion/radiotaxis', 'FeatureCollection', 'swagger-radiotaxis.png');
  await swaggerGet('usuarios', '/api/usuarios/lista', 'admin@colcatrufis.local', 'swagger-usuarios.png');
  await swaggerGet('notificaciones', '/api/notificaciones/historial', 'current_page', 'swagger-historial.png');
  report.status = 'passed';
} catch (error) {
  report.status = 'failed';
  report.error = error.message;
  console.error(error.message);
  if (socket?.readyState === WebSocket.OPEN) await screenshot('panel-fallo.png').catch(() => {});
  process.exitCode = 1;
} finally {
  writeFileSync(path.join(artifacts, 'browser-qa.json'), `${JSON.stringify(report, null, 2)}\n`);
  if (socket?.readyState === WebSocket.OPEN) await send('Browser.close').catch(() => {});
  socket?.close();
  if (browser.exitCode === null) browser.kill();
  // Borrar únicamente el perfil temporal creado por esta ejecución.
  if (path.dirname(path.resolve(profile)) === temporaryRoot && path.basename(profile).startsWith('colcatrufis-qa-')) {
    try { rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch { /* Chrome puede tardar en soltar un archivo temporal */ }
  }
}

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

// Ejecuta el panel real con DOM y HTTP simulados, sin Docker ni paquetes npm.
const source = readFileSync(new URL('../admin-web/app.js', import.meta.url), 'utf8');
const sessionKey = 'colcatrufis.sesion';
const response = (status, body = {}) => ({ status, ok: status >= 200 && status < 300, json: async () => body });

function panel(fetch) {
  const elements = new Map();
  const storage = new Map();
  const element = (id) => {
    if (!elements.has(id)) elements.set(id, {
      hidden: false, disabled: false, textContent: '', listeners: {},
      addEventListener(event, callback) { this.listeners[event] = callback; },
      setAttribute() {},
    });
    return elements.get(id);
  };
  runInNewContext(source, {
    window: { COLCATRUFIS_CONFIG: { gatewayUrl: 'http://gateway' }, addEventListener() {} },
    document: { getElementById: element },
    sessionStorage: {
      getItem: (key) => storage.get(key) ?? null,
      setItem: (key, value) => storage.set(key, value),
      removeItem: (key) => storage.delete(key),
    },
    fetch, clearInterval,
  });
  storage.set(sessionKey, JSON.stringify({ access_token: 'vencido', refresh_token: 'r1' }));
  return {
    logout: () => element('btn-salir').listeners.click(),
    assertClosed() {
      assert.equal(storage.has(sessionKey), false);
      assert.equal(element('vista-login').hidden, false);
      assert.equal(element('vista-mapa').hidden, true);
    },
  };
}

test('salir revoca la sesión vigente y elimina las credenciales locales', async () => {
  const calls = [];
  const app = panel(async (url, options) => {
    calls.push({ url, method: options.method, body: JSON.parse(options.body) });
    return response(204);
  });
  await app.logout();
  assert.deepEqual(calls, [{ url: 'http://gateway/api/usuarios/sesion', method: 'DELETE', body: { refresh_token: 'r1' } }]);
  app.assertClosed();
});

test('salir con JWT vencido renueva y revoca el refresh nuevo', async () => {
  const calls = [];
  const app = panel(async (url, options) => {
    calls.push({ url, method: options.method, auth: options.headers.Authorization, body: JSON.parse(options.body) });
    if (url.endsWith('/refresh')) {
      return response(200, { access_token: 'nuevo', refresh_token: 'r2' });
    }
    if (options.headers.Authorization === 'Bearer vencido') return response(401);
    return response(204);
  });
  await app.logout();
  assert.deepEqual(calls.map(({ url }) => url.split('/').at(-1)), ['sesion', 'refresh', 'sesion']);
  assert.deepEqual(calls[1].body, { refresh_token: 'r1' });
  assert.equal(calls[2].method, 'DELETE');
  assert.equal(calls[2].auth, 'Bearer nuevo');
  assert.deepEqual(calls[2].body, { refresh_token: 'r2' });
  app.assertClosed();
});

test('salir con refresh rechazado termina localmente sin bucle', async () => {
  let calls = 0;
  const app = panel(async () => { calls++; return response(401); });
  await app.logout();
  assert.equal(calls, 2);
  app.assertClosed();
});

test('salir sin conexión también elimina la sesión local', async () => {
  const app = panel(async () => { throw new Error('sin red'); });
  await app.logout();
  app.assertClosed();
});

test('un JWT nuevo rechazado no provoca renovaciones ilimitadas al salir', async () => {
  let calls = 0;
  const app = panel(async (url) => {
    calls++;
    return url.endsWith('/refresh')
      ? response(200, { access_token: 'nuevo', refresh_token: 'r2' })
      : response(401);
  });
  await app.logout();
  assert.equal(calls, 3);
  app.assertClosed();
});

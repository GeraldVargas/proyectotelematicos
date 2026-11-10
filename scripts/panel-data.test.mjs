import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
const source = readFileSync(new URL('../admin-web/app.js', import.meta.url), 'utf8');

for (const tipo of ['paradas', 'radiotaxis']) {
  test(`admin guarda datos de ${tipo} en el Gateway y conserva coordenadas`, async () => {
    const nodes = new Map();
    const calls = [];
    const $ = id => {
      if (!nodes.has(id)) nodes.set(id, {
        value: '', hidden: false, disabled: false, listeners: {},
        elements: Object.fromEntries(['nombre', 'descripcion', 'sindicato', 'telefono', 'lat', 'lng'].map(k => [k, { value: '' }])),
        addEventListener(k, fn) { this.listeners[k] = fn; }, setAttribute() {},
        replaceChildren() { this.value = ''; }, append(o) { if (!this.value) this.value = String(o.value); },
      });
      return nodes.get(id);
    };
    const fixture = { type: 'FeatureCollection', features: [{ id: 7,
      geometry: { type: 'Point', coordinates: [-66.24, -17.38] },
      properties: { nombre: 'Nombre original', telefono: '4441234' } }] };
    runInNewContext(source, {
      window: { addEventListener() {}, COLCATRUFIS_CONFIG: { gatewayUrl: 'http://gateway' } },
      document: { getElementById: $, createElement: () => ({}) },
      sessionStorage: { getItem: () => null }, clearInterval,
      FormData: class { constructor(form) { this.form = form; } get(k) {
        if (k === 'tipo') return tipo;
        if (k === 'id') return $('punto-id').value;
        return this.form.elements[k].value;
      } },
      fetch: async (url, options) => {
        calls.push({ url, ...options });
        return { ok: true, status: 200, json: async () => fixture };
      },
    });
    $('punto-tipo').value = tipo;
    await $('puntos-cargar').listeners.click();
    assert.equal($('punto-estado').textContent, '', 'carga: ' + $('punto-estado').textContent);
    const form = $('form-punto');
    assert.equal($('punto-id').value, '7');
    assert.equal($('punto-guardar').disabled, false);
    assert.equal(form.elements.lat.value, -17.38);
    assert.equal(form.elements.lng.value, -66.24);
    form.elements.nombre.value = 'Nombre actualizado';
    form.elements.telefono.value = '77712345';
    form.elements.descripcion.value = 'Frente a la plaza';
    await form.listeners.submit({ preventDefault() {}, target: form });
    const write = calls.find(c => c.method === 'PUT');
    assert.equal(write.url, `http://gateway/api/ubicacion/${tipo}/7${tipo === 'radiotaxis' ? '/posicion' : ''}`);
    const body = JSON.parse(write.body);
    assert.equal(body.nombre, 'Nombre actualizado');
    assert.equal(body.lat, -17.38);
    assert.equal(body.lng, -66.24);
    if (tipo === 'radiotaxis') assert.equal(body.telefono, '77712345');
    else assert.equal(body.descripcion, 'Frente a la plaza');
  });
}

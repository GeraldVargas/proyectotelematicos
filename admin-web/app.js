(() => {
  'use strict';

  const CFG = window.COLCATRUFIS_CONFIG;
  const ROLES_PANEL = ['admin', 'encargado'];
  const CLAVE_SESION = 'colcatrufis.sesion';

  const $ = (id) => document.getElementById(id);
  let mapa = null;

  // ---------- Sesión (JWT emitido por la API de Usuarios) ----------

  const sesion = {
    leer() {
      try { return JSON.parse(sessionStorage.getItem(CLAVE_SESION)); } catch { return null; }
    },
    guardar(datos) {
      try { sessionStorage.setItem(CLAVE_SESION, JSON.stringify(datos)); } catch { /* modo privado */ }
    },
    borrar() {
      try { sessionStorage.removeItem(CLAVE_SESION); } catch { /* modo privado */ }
    },
  };

  async function login(email, password) {
    const res = await fetch(`${CFG.gatewayUrl}/api/usuarios/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    const cuerpo = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(cuerpo.message || `Error ${res.status}`);
    if (!ROLES_PANEL.includes(cuerpo.usuario?.rol)) {
      throw new Error('Tu usuario no tiene acceso al panel administrativo');
    }
    sesion.guardar(cuerpo);
    return cuerpo;
  }

  async function renovar() {
    const actual = sesion.leer();
    if (!actual?.refresh_token) return false;
    const res = await fetch(`${CFG.gatewayUrl}/api/usuarios/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ refresh_token: actual.refresh_token }),
    });
    if (!res.ok) return false;
    sesion.guardar(await res.json());
    return true;
  }

  /** GET al Gateway con el JWT; si responde 401 intenta un refresh una sola vez. */
  async function api(ruta, reintento = true) {
    const token = sesion.leer()?.access_token;
    const res = await fetch(`${CFG.gatewayUrl}${ruta}`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
    if (res.status === 401) {
      if (reintento && (await renovar())) return api(ruta, false);
      cerrarSesion('Tu sesión expiró. Vuelve a ingresar.');
      throw new Error('No autenticado');
    }
    if (!res.ok) throw new Error(`Error ${res.status} en ${ruta}`);
    return res.json();
  }

  // ---------- Vistas ----------

  function mostrarLogin(mensaje) {
    $('vista-mapa').hidden = true;
    $('vista-login').hidden = false;
    const error = $('login-error');
    error.hidden = !mensaje;
    error.textContent = mensaje || '';
  }

  function cerrarSesion(mensaje) {
    sesion.borrar();
    if (mapa) { mapa.remove(); mapa = null; }
    mostrarLogin(mensaje);
  }

  async function mostrarMapa() {
    const { usuario } = sesion.leer();
    $('usuario-nombre').textContent = usuario.name;
    $('usuario-rol').textContent = usuario.rol;
    $('vista-login').hidden = true;
    $('vista-mapa').hidden = false;
    estado('Cargando rutas…');

    mapa = new maplibregl.Map({
      container: 'mapa',
      style: CFG.estiloMapaBase,
      center: CFG.centro,
      zoom: CFG.zoom,
    });
    mapa.addControl(new maplibregl.NavigationControl(), 'top-right');
    mapa.addControl(new maplibregl.ScaleControl({ unit: 'metric' }));

    try {
      const [rutas, paradas] = await Promise.all([api('/api/ubicacion/rutas'), api('/api/ubicacion/paradas')]);
      if (!mapa) return; // se cerró la sesión mientras cargaba
      if (!mapa.isStyleLoaded()) await new Promise((ok) => mapa.once('load', ok));
      dibujar(rutas, paradas);
      estado(`${rutas.features.length} rutas y ${paradas.features.length} paradas desde PostGIS`);
    } catch (e) {
      if (e.message !== 'No autenticado') estado(e.message);
    }
  }

  function estado(texto) {
    $('estado').textContent = texto;
  }

  // ---------- Mapa ----------

  function dibujar(rutas, paradas) {
    mapa.addSource('rutas', { type: 'geojson', data: rutas });
    mapa.addSource('paradas', { type: 'geojson', data: paradas });

    // Borde claro debajo para que las líneas se lean sobre cualquier fondo
    mapa.addLayer({
      id: 'rutas-borde',
      type: 'line',
      source: 'rutas',
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: { 'line-color': '#ffffff', 'line-width': 7, 'line-opacity': 0.8 },
    });
    mapa.addLayer({
      id: 'rutas',
      type: 'line',
      source: 'rutas',
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: { 'line-color': ['get', 'color'], 'line-width': 4 },
    });
    // line-dasharray no admite expresiones por feature: las suspendidas van en su propia capa punteada
    mapa.addLayer({
      id: 'rutas-suspendidas',
      type: 'line',
      source: 'rutas',
      layout: { 'line-join': 'round' },
      paint: { 'line-color': ['get', 'color'], 'line-width': 4, 'line-dasharray': [2, 2] },
    });
    filtrarRutas(rutas.features.map((f) => f.id));
    mapa.addLayer({
      id: 'paradas',
      type: 'circle',
      source: 'paradas',
      paint: {
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 11, 3, 16, 7],
        'circle-color': '#ffffff',
        'circle-stroke-color': '#1d2733',
        'circle-stroke-width': 2,
      },
    });

    const popupRuta = (e) => {
      const p = e.features[0].properties;
      popup(e.lngLat, `<strong>Línea ${esc(p.linea)}</strong><br>${esc(p.nombre)}<br><small>${esc(p.sindicato || 'Sin sindicato')}</small>`);
    };
    mapa.on('click', 'rutas', popupRuta);
    mapa.on('click', 'rutas-suspendidas', popupRuta);
    mapa.on('click', 'paradas', (e) => {
      const p = e.features[0].properties;
      // MapLibre serializa los arrays de properties como texto
      const lineas = JSON.parse(p.rutas || '[]').map((r) => r.linea).join(', ');
      popup(e.lngLat, `<strong>${esc(p.nombre)}</strong><br><small>Líneas: ${esc(lineas)}</small>`);
    });
    for (const capa of ['rutas', 'rutas-suspendidas', 'paradas']) {
      mapa.on('mouseenter', capa, () => { mapa.getCanvas().style.cursor = 'pointer'; });
      mapa.on('mouseleave', capa, () => { mapa.getCanvas().style.cursor = ''; });
    }

    listarRutas(rutas.features);
    encuadrar(rutas.features);
  }

  function listarRutas(features) {
    const lista = $('lista-rutas');
    lista.replaceChildren();
    $('rutas-total').textContent = `(${features.length})`;
    const visibles = new Set(features.map((f) => f.id));

    for (const f of features) {
      const li = document.createElement('li');
      li.innerHTML = `<label>
          <input type="checkbox" checked>
          <span class="color" style="background:${esc(f.properties.color)}"></span>
          <span><span class="linea">Línea ${esc(f.properties.linea)}</span>
          <span class="sindicato">${esc(f.properties.sindicato || '')}</span></span>
        </label>`;
      li.querySelector('input').addEventListener('change', (e) => {
        e.target.checked ? visibles.add(f.id) : visibles.delete(f.id);
        filtrarRutas([...visibles]);
      });
      lista.append(li);
    }
  }

  function filtrarRutas(ids) {
    const visible = ['in', ['id'], ['literal', ids]];
    const suspendida = ['==', ['get', 'estado'], 'suspendida'];
    mapa.setFilter('rutas-borde', visible);
    mapa.setFilter('rutas', ['all', visible, ['!', suspendida]]);
    mapa.setFilter('rutas-suspendidas', ['all', visible, suspendida]);
  }

  function encuadrar(features) {
    const limites = new maplibregl.LngLatBounds();
    features.forEach((f) => f.geometry.coordinates.forEach((c) => limites.extend(c)));
    if (!limites.isEmpty()) mapa.fitBounds(limites, { padding: 40, duration: 0 });
  }

  function popup(lngLat, html) {
    new maplibregl.Popup({ closeButton: true }).setLngLat(lngLat).setHTML(html).addTo(mapa);
  }

  function esc(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  // ---------- Eventos ----------

  $('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData(e.target);
    const boton = e.target.querySelector('button');
    boton.disabled = true;
    try {
      await login(datos.get('email'), datos.get('password'));
      e.target.reset();
      await mostrarMapa();
    } catch (err) {
      mostrarLogin(err.message === 'Failed to fetch' ? 'No se pudo conectar con el API Gateway' : err.message);
    } finally {
      boton.disabled = false;
    }
  });

  $('btn-salir').addEventListener('click', () => cerrarSesion());

  $('toggle-paradas').addEventListener('change', (e) => {
    if (mapa?.getLayer('paradas')) mapa.setLayoutProperty('paradas', 'visibility', e.target.checked ? 'visible' : 'none');
  });

  sesion.leer()?.access_token ? mostrarMapa() : mostrarLogin();
})();

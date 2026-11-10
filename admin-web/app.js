(() => {
  'use strict';

  const CFG = window.COLCATRUFIS_CONFIG;
  const ROLES_PANEL = ['admin', 'encargado'];
  const CLAVE_SESION = 'colcatrufis.sesion';
  const INTERVALO_RADIOTAXIS_MS = 30000;

  const $ = (id) => document.getElementById(id);
  let mapa = null;
  let popupAbierto = null;
  let datosRutas = null;
  let editor = null;
  let eligiendoCercanas = false;
  let temporizadorRadiotaxis = null;
  let paginaUsuarios = 1;
  let renovando = null;

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

  // Todas las solicitudes que reciban 401 comparten la misma rotación de refresh token.
  // Sin este candado, dos peticiones paralelas invalidan la sesión aun si una renovación funcionó.
  function renovar() {
    renovando ??= pedirRenovacion().finally(() => { renovando = null; });
    return renovando;
  }

  async function pedirRenovacion() {
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

  /** Revoca el refresh token en el servidor. Si falla, la sesión local se cierra igual. */
  async function revocarSesion() {
    try {
      // Una consulta del mapa puede estar rotando el refresh al pulsar Salir.
      if (renovando) await renovando;
      const enviar = (actual) => fetch(`${CFG.gatewayUrl}/api/usuarios/sesion`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${actual.access_token}` },
        body: JSON.stringify({ refresh_token: actual.refresh_token }),
      });
      const actual = sesion.leer();
      if (!actual?.access_token) return;
      const res = await enviar(actual);
      if (res.status === 401 && (await renovar())) {
        // Usar el refresh recién emitido: el anterior ya se consumió al renovar.
        await enviar(sesion.leer());
      }
    } catch { /* sin conexión: el refresh token expirará solo */ }
  }

  /** GET al Gateway con el JWT; si responde 401 intenta un refresh una sola vez. */
  async function api(ruta, opciones = {}, reintento = true) {
    const token = sesion.leer()?.access_token;
    const res = await fetch(`${CFG.gatewayUrl}${ruta}`, {
      method: opciones.metodo || 'GET',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(opciones.cuerpo ? { 'Content-Type': 'application/json' } : {}) },
      ...(opciones.cuerpo ? { body: JSON.stringify(opciones.cuerpo) } : {}),
    });
    if (res.status === 401) {
      if (reintento && (await renovar())) return api(ruta, opciones, false);
      cerrarSesion('Tu sesión expiró. Vuelve a ingresar.');
      throw new Error('No autenticado');
    }
    const cuerpo = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(cuerpo.message || `Error ${res.status} en ${ruta}`);
    return cuerpo;
  }

  let puntosEditables = [];
  function seleccionarRegistroPunto() {
    const form = $('form-punto');
    const feature = puntosEditables.find(f => String(f.id) === $('punto-id').value);
    $('punto-guardar').disabled = !feature;
    if (!feature) return;
    for (const campo of ['nombre', 'descripcion', 'sindicato', 'telefono']) {
      form.elements[campo].value = feature.properties[campo] ?? '';
    }
    form.elements.lat.value = feature.geometry.coordinates[1];
    form.elements.lng.value = feature.geometry.coordinates[0];
  }
  async function cargarPuntos() {
    const tipo = $('punto-tipo').value;
    puntosEditables = [];
    $('punto-id').replaceChildren();
    $('punto-guardar').disabled = true;
    $('punto-parada').hidden = tipo !== 'paradas';
    $('punto-taxi').hidden = tipo !== 'radiotaxis';
    try {
      const datos = await api(`/api/ubicacion/${tipo}`);
      if ($('punto-tipo').value !== tipo) return;
      puntosEditables = datos.features;
      for (const feature of puntosEditables) {
        const option = document.createElement('option');
        option.value = feature.id;
        option.textContent = feature.properties.nombre;
        $('punto-id').append(option);
      }
      seleccionarRegistroPunto();
      $('punto-estado').textContent = puntosEditables.length ? '' : 'No hay registros disponibles.';
    } catch (error) { $('punto-estado').textContent = error.message; }
  }
  $('puntos-cargar').addEventListener('click', cargarPuntos);
  $('punto-tipo').addEventListener('change', cargarPuntos);
  $('punto-id').addEventListener('change', seleccionarRegistroPunto);
  $('form-punto').addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData(e.target);
    const tipo = datos.get('tipo');
    const id = datos.get('id');
    if (!puntosEditables.some(f => String(f.id) === id)) return;
    const cuerpo = { nombre: datos.get('nombre'), lat: Number(datos.get('lat')), lng: Number(datos.get('lng')) };
    if (tipo === 'paradas') cuerpo.descripcion = datos.get('descripcion') || null;
    else { cuerpo.sindicato = datos.get('sindicato') || null; cuerpo.telefono = datos.get('telefono') || null; }
    $('punto-guardar').disabled = true;
    try {
      await api(`/api/ubicacion/${tipo}/${id}${tipo === 'radiotaxis' ? '/posicion' : ''}`, { metodo: 'PUT', cuerpo });
      await cargarDatos();
      await cargarPuntos();
      $('punto-estado').textContent = 'Guardado. En la app clásica usa Actualizar datos.';
    } catch (error) { $('punto-estado').textContent = error.message; }
    finally { $('punto-guardar').disabled = !puntosEditables.length; }
  });

  // ---------- Vistas ----------

  function mostrarLogin(mensaje) {
    $('vista-mapa').hidden = true;
    $('vista-login').hidden = false;
    const error = $('login-error');
    error.hidden = !mensaje;
    error.textContent = mensaje || '';
  }

  function cerrarSesion(mensaje) {
    popupAbierto?.remove();
    popupAbierto = null;
    editor = null;
    clearInterval(temporizadorRadiotaxis);
    temporizadorRadiotaxis = null;
    $('editor-ruta').hidden = true;
    $('seccion-usuarios').hidden = true;
    $('panel-usuarios').hidden = true;
    $('btn-usuarios').setAttribute('aria-expanded', 'false');
    $('usuarios-total').textContent = '';
    paginaUsuarios = 1;
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
    $('seccion-usuarios').hidden = usuario.rol !== 'admin';
    $('edicion-puntos').hidden = usuario.rol !== 'admin';
    estado('Cargando rutas…');

    mapa = new maplibregl.Map({
      container: 'mapa',
      style: CFG.estiloMapaBase,
      center: CFG.centro,
      zoom: CFG.zoom,
    });
    mapa.addControl(new maplibregl.NavigationControl(), 'top-right');
    mapa.addControl(new maplibregl.ScaleControl({ unit: 'metric' }));
    mapa.on('click', (e) => {
      if (editor?.agregando) {
        editor.coordenadas.push([e.lngLat.lng, e.lngLat.lat]);
        actualizarEdicion();
      } else if (eligiendoCercanas && !editor) {
        buscarCercanas(e.lngLat);
      }
    });
    mapa.on('mousemove', (e) => {
      if (editor?.arrastrando == null) return;
      editor.coordenadas[editor.arrastrando] = [e.lngLat.lng, e.lngLat.lat];
      actualizarEdicion();
    });

    await cargarDatos();
  }

  async function cargarDatos() {
    try {
      const [rutas, paradas] = await Promise.all([api('/api/ubicacion/rutas'), api('/api/ubicacion/paradas')]);
      if (!mapa) return; // se cerró la sesión mientras cargaba
      if (!mapa.isStyleLoaded()) await new Promise((ok) => mapa.once('load', ok));
      datosRutas = rutas;
      if (mapa.getSource('rutas')) {
        mapa.getSource('rutas').setData(rutas);
        mapa.getSource('paradas').setData(paradas);
        listarRutas(rutas.features);
      } else {
        dibujar(rutas, paradas);
      }
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

    mapa.addSource('radiotaxis', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    mapa.addLayer({
      id: 'radiotaxis',
      type: 'circle',
      source: 'radiotaxis',
      paint: {
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 11, 5, 16, 9],
        'circle-color': '#facc15',
        'circle-stroke-color': '#1d2733',
        'circle-stroke-width': 2,
      },
    });
    actualizarRadiotaxis();
    clearInterval(temporizadorRadiotaxis);
    temporizadorRadiotaxis = setInterval(actualizarRadiotaxis, INTERVALO_RADIOTAXIS_MS);

    const popupRuta = (e) => {
      if (editor || eligiendoCercanas) return;
      const p = e.features[0].properties;
      popup(e.lngLat, `<strong>Línea ${esc(p.linea)}</strong><br>${esc(p.nombre)}<br><small>${esc(p.sindicato || 'Sin sindicato')}</small>`);
    };
    mapa.on('click', 'rutas', popupRuta);
    mapa.on('click', 'rutas-suspendidas', popupRuta);
    mapa.on('click', 'paradas', (e) => {
      if (editor || eligiendoCercanas) return;
      const p = e.features[0].properties;
      // MapLibre serializa los arrays de properties como texto
      const lineas = JSON.parse(p.rutas || '[]').map((r) => r.linea).join(', ');
      popup(e.lngLat, `<strong>${esc(p.nombre)}</strong><br><small>Líneas: ${esc(lineas)}</small>`);
    });
    // Al final: un radio taxi suele estar sobre una ruta y su detalle debe prevalecer al tocarlo.
    mapa.on('click', 'radiotaxis', (e) => {
      if (editor || eligiendoCercanas) return;
      const p = e.features[0].properties;
      const demo = p.origen === 'demo' ? '<br><small>Datos de demostración</small>' : '';
      popup(e.lngLat, `<strong>${esc(p.nombre)}</strong><br>${esc(p.telefono || 'Sin teléfono')}${demo}`);
    });
    for (const capa of ['rutas', 'rutas-suspendidas', 'paradas', 'radiotaxis']) {
      mapa.on('mouseenter', capa, () => { mapa.getCanvas().style.cursor = 'pointer'; });
      mapa.on('mouseleave', capa, () => { mapa.getCanvas().style.cursor = ''; });
    }

    listarRutas(rutas.features);
    encuadrar(rutas.features);
  }

  async function actualizarRadiotaxis() {
    try {
      const radiotaxis = await api('/api/ubicacion/radiotaxis');
      mapa?.getSource('radiotaxis')?.setData(radiotaxis);
      $('radiotaxis-total').textContent = `(${radiotaxis.features.length})`;
    } catch (e) {
      // Un fallo de esta capa no debe interrumpir el resto del panel; reintenta en el próximo ciclo
      if (e.message !== 'No autenticado') $('radiotaxis-total').textContent = '(sin datos)';
    }
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
      if (sesion.leer()?.usuario?.rol === 'admin') {
        const editar = document.createElement('button');
        editar.type = 'button';
        editar.className = 'secundario ruta-editar';
        editar.textContent = 'Editar trazado';
        editar.setAttribute('aria-label', `Editar línea ${f.properties.linea}`);
        editar.addEventListener('click', () => iniciarEdicion(f));
        li.append(editar);
      }
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
    popupAbierto?.remove();
    popupAbierto = new maplibregl.Popup({ closeButton: true }).setLngLat(lngLat).setHTML(html).addTo(mapa);
  }

  function esc(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  // ---------- Edición del trazado (Sprint 2) ----------

  function iniciarEdicion(feature) {
    popupAbierto?.remove();
    popupAbierto = null;
    cancelarEdicion();
    eligiendoCercanas = false;
    $('btn-cercanas').setAttribute('aria-pressed', 'false');
    editor = { feature, coordenadas: structuredClone(feature.geometry.coordinates), seleccionado: null, arrastrando: null, agregando: false };
    $('editor-linea').textContent = feature.properties.linea;
    const form = $('form-ruta');
    form.reset();
    for (const campo of ['nombre', 'estado', 'color', 'sindicato', 'horario', 'tarifa', 'frecuencia_min']) form.elements[campo].value = feature.properties[campo] ?? '';
    $('editor-error').hidden = true;
    $('editor-ruta').hidden = false;
    mapa.addSource('edicion', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    mapa.addLayer({ id: 'edicion-linea', type: 'line', source: 'edicion', filter: ['==', ['geometry-type'], 'LineString'], paint: { 'line-color': '#f59e0b', 'line-width': 5 } });
    mapa.addLayer({ id: 'edicion-puntos', type: 'circle', source: 'edicion', filter: ['==', ['geometry-type'], 'Point'], paint: {
      'circle-color': ['case', ['get', 'seleccionado'], '#e11d48', '#ffffff'], 'circle-radius': 6,
      'circle-stroke-color': '#92400e', 'circle-stroke-width': 2,
    } });
    mapa.on('mousedown', 'edicion-puntos', seleccionarPunto);
    actualizarEdicion();
    encuadrar([feature]);
    $('editor-ruta').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function seleccionarPunto(e) {
    if (!editor || editor.agregando) return;
    e.preventDefault();
    editor.seleccionado = Number(e.features[0].properties.indice);
    editor.arrastrando = editor.seleccionado;
    mapa.dragPan.disable();
    actualizarEdicion();
  }

  function actualizarEdicion() {
    if (!editor || !mapa) return;
    mapa.getSource('edicion').setData({ type: 'FeatureCollection', features: [
      { type: 'Feature', geometry: { type: 'LineString', coordinates: editor.coordenadas }, properties: {} },
      ...editor.coordenadas.map((coordinates, indice) => ({ type: 'Feature', geometry: { type: 'Point', coordinates }, properties: { indice, seleccionado: indice === editor.seleccionado } })),
    ] });
    $('editor-quitar').disabled = editor.seleccionado == null || editor.coordenadas.length <= 2;
    $('editor-agregar').setAttribute('aria-pressed', String(editor.agregando));
    mapa.getCanvas().style.cursor = editor.agregando ? 'crosshair' : '';
  }

  function cancelarEdicion() {
    if (mapa) {
      mapa.off('mousedown', 'edicion-puntos', seleccionarPunto);
      for (const id of ['edicion-puntos', 'edicion-linea']) if (mapa.getLayer(id)) mapa.removeLayer(id);
      if (mapa.getSource('edicion')) mapa.removeSource('edicion');
      mapa.dragPan.enable();
      mapa.getCanvas().style.cursor = '';
    }
    editor = null;
    $('editor-ruta').hidden = true;
  }

  async function buscarCercanas(punto) {
    eligiendoCercanas = false;
    $('btn-cercanas').setAttribute('aria-pressed', 'false');
    estado('Buscando paradas cercanas…');
    try {
      const resultado = await api(`/api/ubicacion/paradas/cercanas?lat=${punto.lat}&lng=${punto.lng}&radio=300`);
      $('lista-cercanas').replaceChildren();
      for (const feature of resultado.features) {
        const li = document.createElement('li');
        li.textContent = `${feature.properties.nombre} · ${Math.round(feature.properties.distancia_m)} m`;
        $('lista-cercanas').append(li);
      }
      estado(resultado.features.length ? `${resultado.features.length} paradas a menos de 300 m` : 'No hay paradas a menos de 300 m');
    } catch (error) { estado(error.message); }
  }

  // ---------- Usuarios (solo admin) ----------

  async function cargarUsuarios(pagina) {
    try {
      const resultado = await api(`/api/usuarios/lista?page=${pagina}&por_pagina=10`);
      paginaUsuarios = resultado.current_page;
      $('lista-usuarios').replaceChildren(...resultado.data.map((u) => {
        const li = document.createElement('li');
        li.innerHTML = `<strong>${esc(u.name)}</strong> <span class="rol">${esc(u.rol)}</span>${u.activo ? '' : ' <span class="inactivo">desactivado</span>'}<br><small>${esc(u.email)}</small>`;
        return li;
      }));
      $('usuarios-total').textContent = `(${resultado.total})`;
      $('usuarios-pagina').textContent = `${resultado.current_page} / ${resultado.last_page}`;
      $('usuarios-anterior').disabled = resultado.current_page <= 1;
      $('usuarios-siguiente').disabled = resultado.current_page >= resultado.last_page;
    } catch (e) {
      if (e.message !== 'No autenticado') estado(e.message);
    }
  }

  $('btn-usuarios').addEventListener('click', () => {
    const abrir = $('panel-usuarios').hidden;
    $('panel-usuarios').hidden = !abrir;
    $('btn-usuarios').setAttribute('aria-expanded', String(abrir));
    $('btn-usuarios').textContent = abrir ? 'Ocultar usuarios' : 'Ver usuarios';
    if (abrir) cargarUsuarios(paginaUsuarios);
  });
  $('usuarios-anterior').addEventListener('click', () => cargarUsuarios(paginaUsuarios - 1));
  $('usuarios-siguiente').addEventListener('click', () => cargarUsuarios(paginaUsuarios + 1));

  $('form-ruta').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!editor) return;
    const datos = new FormData(e.target);
    $('editor-guardar').disabled = true;
    $('editor-error').hidden = true;
    try {
      await api(`/api/ubicacion/rutas/${editor.feature.id}`, { metodo: 'PUT', cuerpo: {
        geometry: { type: 'LineString', coordinates: editor.coordenadas },
        nombre: datos.get('nombre'), estado: datos.get('estado'), color: datos.get('color'), descripcion: datos.get('descripcion'),
        sindicato: datos.get('sindicato') || null, horario: datos.get('horario') || null,
        tarifa: datos.get('tarifa') === '' ? null : Number(datos.get('tarifa')),
        frecuencia_min: datos.get('frecuencia_min') === '' ? null : Number(datos.get('frecuencia_min')),
      } });
      cancelarEdicion();
      await cargarDatos();
      estado('Ruta guardada. Se enviará un aviso a los usuarios suscritos.');
    } catch (error) {
      $('editor-error').textContent = error.message;
      $('editor-error').hidden = false;
    } finally { $('editor-guardar').disabled = false; }
  });
  $('editor-cancelar').addEventListener('click', cancelarEdicion);
  $('editor-agregar').addEventListener('click', () => { if (editor) { editor.agregando = !editor.agregando; actualizarEdicion(); } });
  $('editor-restablecer').addEventListener('click', () => {
    if (!editor) return;
    editor.coordenadas = structuredClone(editor.feature.geometry.coordinates);
    editor.seleccionado = null;
    actualizarEdicion();
  });
  $('editor-quitar').addEventListener('click', () => {
    if (!editor || editor.seleccionado == null || editor.coordenadas.length <= 2) return;
    editor.coordenadas.splice(editor.seleccionado, 1);
    editor.seleccionado = null;
    actualizarEdicion();
  });
  $('btn-cercanas').addEventListener('click', () => {
    cancelarEdicion();
    eligiendoCercanas = !eligiendoCercanas;
    $('btn-cercanas').setAttribute('aria-pressed', String(eligiendoCercanas));
    estado(eligiendoCercanas ? 'Selecciona una ubicación en el mapa.' : 'Búsqueda cancelada.');
  });

  // ---------- Eventos ----------

  // En window y no en el mapa: soltar sobre un control del mapa también debe terminar el arrastre.
  window.addEventListener('mouseup', () => {
    if (editor) editor.arrastrando = null;
    mapa?.dragPan.enable();
  });

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

  $('btn-salir').addEventListener('click', async () => {
    await revocarSesion();
    cerrarSesion();
  });

  $('toggle-paradas').addEventListener('change', (e) => {
    if (mapa?.getLayer('paradas')) mapa.setLayoutProperty('paradas', 'visibility', e.target.checked ? 'visible' : 'none');
  });

  $('toggle-radiotaxis').addEventListener('change', (e) => {
    if (mapa?.getLayer('radiotaxis')) mapa.setLayoutProperty('radiotaxis', 'visibility', e.target.checked ? 'visible' : 'none');
  });

  sesion.leer()?.access_token ? mostrarMapa() : mostrarLogin();
})();

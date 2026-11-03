import 'dart:async';
import 'dart:math' show Point;

import 'package:flutter/material.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../../core/network/gateway_client.dart';
import '../../core/network/gateway_config.dart';
import '../../core/servicios.dart';

/// Mapa vectorial (MapLibre + OpenFreeMap) con las rutas, paradas y radio taxis servidos como
/// GeoJSON por la API de Ubicación a través del Gateway.
class MapaRutasScreen extends StatefulWidget {
  const MapaRutasScreen({super.key});

  @override
  State<MapaRutasScreen> createState() => _MapaRutasScreenState();
}

class _MapaRutasScreenState extends State<MapaRutasScreen> {
  static const _colcapirhua = LatLng(-17.3895, -66.2405);
  static const _capasRutas = {'rutas-linea', 'rutas-borde'};
  static const _capaRadiotaxis = 'radiotaxis-punto';

  MapLibreMapController? _mapa;
  StreamSubscription<void>? _expiro;
  Timer? _refrescoRadiotaxis;
  bool _cargando = true;
  String? _error;
  int _totalRutas = 0;
  final Map<String, Map<String, dynamic>> _rutasPorId = {};
  final Map<String, Map<String, dynamic>> _radiotaxisPorId = {};

  @override
  void initState() {
    super.initState();
    _expiro = Servicios.gateway.sesionExpirada.listen((_) {
      if (mounted) Navigator.pushNamedAndRemoveUntil(context, '/login', (_) => false);
    });
    Servicios.push.iniciar();
  }

  @override
  void dispose() {
    _expiro?.cancel();
    _refrescoRadiotaxis?.cancel();
    super.dispose();
  }

  Future<void> _alCargarEstilo() async {
    final mapa = _mapa;
    if (mapa == null) return;
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      final rutas = await Servicios.ubicacion.rutas();
      final paradas = await Servicios.ubicacion.paradas();

      await mapa.addGeoJsonSource('rutas', rutas);
      await mapa.addGeoJsonSource('paradas', paradas);
      await mapa.addLineLayer(
        'rutas',
        'rutas-borde',
        const LineLayerProperties(lineColor: '#FFFFFF', lineWidth: 7.0, lineOpacity: 0.8, lineJoin: 'round', lineCap: 'round'),
      );
      await mapa.addLineLayer(
        'rutas',
        'rutas-linea',
        const LineLayerProperties(
          // Un color por línea, definido en la BD de Ubicación
          lineColor: ['get', 'color'],
          lineWidth: 4.0,
          lineJoin: 'round',
          lineCap: 'round',
        ),
      );
      await mapa.addCircleLayer(
        'paradas',
        'paradas-punto',
        const CircleLayerProperties(
          circleRadius: 5.0,
          circleColor: '#FFFFFF',
          circleStrokeColor: '#1D2733',
          circleStrokeWidth: 2.0,
        ),
      );

      await _iniciarRadiotaxis(mapa);

      final features = (rutas['features'] as List).cast<Map>();
      _rutasPorId
        ..clear()
        ..addEntries(features.map((f) => MapEntry('${f['id']}', Map<String, dynamic>.from(f['properties'] as Map))));
      await _encuadrar(features);
      setState(() => _totalRutas = features.length);
    } on ApiException catch (e) {
      if (e.statusCode != 401) setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  /// Un fallo en esta capa no impide usar el mapa de rutas: se reintenta en el siguiente ciclo.
  Future<void> _iniciarRadiotaxis(MapLibreMapController mapa) async {
    await mapa.addGeoJsonSource('radiotaxis', const {'type': 'FeatureCollection', 'features': []});
    await mapa.addCircleLayer(
      'radiotaxis',
      _capaRadiotaxis,
      const CircleLayerProperties(
        circleRadius: 8.0,
        circleColor: '#FACC15',
        circleStrokeColor: '#1D2733',
        circleStrokeWidth: 2.0,
      ),
    );
    await _actualizarRadiotaxis();
    _refrescoRadiotaxis?.cancel();
    _refrescoRadiotaxis = Timer.periodic(GatewayConfig.refrescoRadiotaxis, (_) => _actualizarRadiotaxis());
  }

  Future<void> _actualizarRadiotaxis() async {
    try {
      final datos = await Servicios.ubicacion.radiotaxis();
      if (!mounted) return;
      await _mapa?.setGeoJsonSource('radiotaxis', datos);
      _radiotaxisPorId
        ..clear()
        ..addEntries((datos['features'] as List).cast<Map>().map(
              (f) => MapEntry('${f['id']}', Map<String, dynamic>.from(f['properties'] as Map)),
            ));
    } on ApiException {
      // Se conservan las últimas posiciones conocidas
    }
  }

  Future<void> _encuadrar(List<Map> features) async {
    double? s, w, n, e;
    for (final f in features) {
      for (final c in (f['geometry']['coordinates'] as List)) {
        // GeoJSON: [lng, lat]
        final lng = (c[0] as num).toDouble();
        final lat = (c[1] as num).toDouble();
        s = s == null || lat < s ? lat : s;
        n = n == null || lat > n ? lat : n;
        w = w == null || lng < w ? lng : w;
        e = e == null || lng > e ? lng : e;
      }
    }
    if (s == null) return;
    await _mapa?.moveCamera(CameraUpdate.newLatLngBounds(
      LatLngBounds(southwest: LatLng(s, w!), northeast: LatLng(n!, e!)),
      left: 32, top: 32, right: 32, bottom: 32,
    ));
  }

  /// Al tocar una ruta se muestra su número y su sindicato; al tocar un radio taxi, su contacto.
  /// Las capas GeoJSON son interactivas: MapLibre entrega el `id` del feature.
  void _alTocarFeature(Point<double> _, LatLng __, String id, String capa, Annotation? ___) {
    if (!mounted) return;
    final Widget? detalle;
    if (capa == _capaRadiotaxis) {
      final propiedades = _radiotaxisPorId[id];
      detalle = propiedades == null ? null : _DetalleRadiotaxi(propiedades: propiedades);
    } else if (_capasRutas.contains(capa)) {
      final propiedades = _rutasPorId[id];
      detalle = propiedades == null ? null : _DetalleRuta(propiedades: propiedades);
    } else {
      detalle = null;
    }
    if (detalle == null) return;

    showModalBottomSheet<void>(context: context, showDragHandle: true, builder: (_) => detalle!);
  }

  Future<void> _salir() async {
    await Servicios.push.detener();
    await Servicios.auth.logout();
    if (mounted) Navigator.pushNamedAndRemoveUntil(context, '/login', (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_totalRutas > 0 ? 'Rutas de trufi ($_totalRutas)' : 'Rutas de trufi'),
        actions: [
          IconButton(
            tooltip: 'Avisos por línea',
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => Navigator.pushNamed(context, '/suscripciones'),
          ),
          IconButton(
            tooltip: 'App clásica',
            icon: const Icon(Icons.view_list),
            onPressed: () => Navigator.pushNamed(context, '/home'),
          ),
          IconButton(tooltip: 'Cerrar sesión', icon: const Icon(Icons.logout), onPressed: _salir),
        ],
      ),
      body: Stack(
        children: [
          MapLibreMap(
            styleString: GatewayConfig.estiloMapaBase,
            initialCameraPosition: const CameraPosition(target: _colcapirhua, zoom: 13),
            onMapCreated: (c) => _mapa = c..onFeatureTapped.add(_alTocarFeature),
            onStyleLoadedCallback: _alCargarEstilo,
            compassEnabled: true,
          ),
          if (_cargando) const LinearProgressIndicator(),
          if (_error != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: Material(
                elevation: 4,
                borderRadius: BorderRadius.circular(12),
                color: Theme.of(context).colorScheme.errorContainer,
                child: ListTile(
                  leading: const Icon(Icons.cloud_off),
                  title: Text(_error!),
                  trailing: TextButton(onPressed: _alCargarEstilo, child: const Text('Reintentar')),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _DetalleRuta extends StatelessWidget {
  final Map<String, dynamic> propiedades;

  const _DetalleRuta({required this.propiedades});

  Color _color(String? hex) {
    final valor = int.tryParse((hex ?? '').replaceFirst('#', ''), radix: 16);
    return valor == null ? Colors.blueGrey : Color(0xFF000000 | valor);
  }

  @override
  Widget build(BuildContext context) {
    final tema = Theme.of(context);
    final p = propiedades;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Container(width: 20, height: 6, decoration: BoxDecoration(color: _color(p['color'] as String?), borderRadius: BorderRadius.circular(3))),
            const SizedBox(width: 12),
            Text('Línea ${p['linea']}', style: tema.textTheme.titleLarge),
          ]),
          const SizedBox(height: 8),
          Text('${p['nombre'] ?? ''}', style: tema.textTheme.bodyMedium),
          const SizedBox(height: 12),
          Text('Sindicato', style: tema.textTheme.labelMedium),
          Text('${p['sindicato'] ?? 'Sin información'}', style: tema.textTheme.bodyLarge),
          if (p['tarifa'] != null) ...[
            const SizedBox(height: 8),
            Text('Tarifa: Bs ${p['tarifa']}'),
          ],
        ],
      ),
    );
  }
}

class _DetalleRadiotaxi extends StatelessWidget {
  final Map<String, dynamic> propiedades;

  const _DetalleRadiotaxi({required this.propiedades});

  @override
  Widget build(BuildContext context) {
    final tema = Theme.of(context);
    final p = propiedades;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${p['nombre']}', style: tema.textTheme.titleLarge),
          const SizedBox(height: 8),
          Text('Teléfono: ${p['telefono'] ?? 'Sin información'}', style: tema.textTheme.bodyLarge),
          if (p['origen'] == 'demo') ...[
            const SizedBox(height: 8),
            Text('Datos de demostración', style: tema.textTheme.labelMedium),
          ],
        ],
      ),
    );
  }
}

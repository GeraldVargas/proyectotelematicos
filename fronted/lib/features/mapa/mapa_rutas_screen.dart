import 'dart:async';
import 'dart:math' show Point;

import 'package:flutter/material.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../../core/network/gateway_client.dart';
import '../../core/network/gateway_config.dart';
import '../../core/servicios.dart';

/// Prueba de concepto del Sprint 1: mapa vectorial (MapLibre + OpenFreeMap) con las rutas
/// servidas como GeoJSON por la API de Ubicación a través del Gateway.
class MapaRutasScreen extends StatefulWidget {
  const MapaRutasScreen({super.key});

  @override
  State<MapaRutasScreen> createState() => _MapaRutasScreenState();
}

class _MapaRutasScreenState extends State<MapaRutasScreen> {
  static const _colcapirhua = LatLng(-17.3895, -66.2405);
  static const _capasRutas = {'rutas-linea', 'rutas-borde'};

  MapLibreMapController? _mapa;
  StreamSubscription<void>? _expiro;
  bool _cargando = true;
  String? _error;
  int _totalRutas = 0;
  final Map<String, Map<String, dynamic>> _rutasPorId = {};

  @override
  void initState() {
    super.initState();
    _expiro = Servicios.gateway.sesionExpirada.listen((_) {
      if (mounted) Navigator.pushNamedAndRemoveUntil(context, '/login', (_) => false);
    });
  }

  @override
  void dispose() {
    _expiro?.cancel();
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

  /// Al tocar una ruta se muestra su número y su sindicato.
  /// Las capas GeoJSON son interactivas: MapLibre entrega el `id` del feature (id de la ruta).
  void _alTocarFeature(Point<double> _, LatLng __, String id, String capa, Annotation? ___) {
    if (!_capasRutas.contains(capa)) return;
    final propiedades = _rutasPorId[id];
    if (propiedades == null || !mounted) return;

    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (_) => _DetalleRuta(propiedades: propiedades),
    );
  }

  Future<void> _salir() async {
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

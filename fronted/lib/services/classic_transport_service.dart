import '../features/mapa/ubicacion_service.dart';
import 'api_service.dart';

/// Adapta los contratos GeoJSON a los campos de la interfaz clásica.
/// No mezcla identificadores del Gateway con los del monolito.
class ClassicTransportService extends ApiService {
  final UbicacionService ubicacion;
  bool useGateway;
  Future<Map<String, dynamic>>? _rutas;

  ClassicTransportService({required super.baseUrl, required this.ubicacion,
    this.useGateway = false});

  void invalidarCatalogo() => _rutas = null;

  Future<Map<String, dynamic>> _catalogo() async {
    try {
      return await (_rutas ??= ubicacion.rutas());
    } catch (_) {
      _rutas = null;
      rethrow;
    }
  }

  List<Map<String, dynamic>> _features(Map<String, dynamic> geo) =>
      (geo['features'] as List).map((f) => Map<String, dynamic>.from(f as Map)).toList();

  Map<String, dynamic> _punto(Map<String, dynamic> f) {
    final p = Map<String, dynamic>.from(f['properties'] as Map);
    final c = f['geometry']['coordinates'] as List;
    return {...p, 'id': p['id'] ?? f['id'], 'latitud': c[1], 'longitud': c[0]};
  }

  @override
  Future<List<dynamic>> getTrufis() async {
    if (!useGateway) return getClassicList('/trufis');
    return _features(await _catalogo()).map((f) {
      final p = f['properties'] as Map;
      return <String, dynamic>{...Map<String, dynamic>.from(p),
        'idtrufi': p['id'] ?? f['id'], 'nom_linea': p['linea'],
        'sindicato': {'nombre': p['sindicato'] ?? 'Sin sindicato'}};
    }).toList();
  }

  @override
  Future<List<dynamic>> getSindicatos() async {
    if (!useGateway) return getClassicList('/sindicatos');
    final grupos = <String, List<dynamic>>{};
    for (final t in await getTrufis()) {
      final nombre = t['sindicato']['nombre'] as String;
      grupos.putIfAbsent(nombre, () => []).add(t);
    }
    return grupos.entries.map((e) => {'nombre': e.key, 'trufis': e.value}).toList();
  }

  @override
  Future<List<dynamic>> getRadioTaxis() async {
    if (!useGateway) return getClassicList('/radiotaxis');
    return _features(await ubicacion.radiotaxis()).map((f) {
      final p = _punto(f);
      return {...p, 'nombre_comercial': p['nombre'], 'sindicato_radiotaxi_id': p['id']};
    }).toList();
  }

  Future<List<dynamic>> getParadasRadiotaxis() async {
    if (!useGateway) return getClassicList('/radiotaxis/paradas');
    // Las posiciones actuales se cargan con getRadioTaxis y se refrescan en el mapa.
    return [];
  }

  @override
  Future<Map<String, dynamic>> getGeoJsonTodasRutas() async {
    if (!useGateway) return super.getGeoJsonTodasRutas();
    final geo = await _catalogo();
    return {...geo, 'features': _features(geo).map((f) => {...f,
      'properties': {...Map<String, dynamic>.from(f['properties'] as Map),
        'idtrufi': f['properties']['id'] ?? f['id']}}).toList()};
  }

  @override
  Future<Map<String, dynamic>> getGeoJsonPorTrufi(int idtrufi) async {
    if (!useGateway) return super.getGeoJsonPorTrufi(idtrufi);
    final geo = await getGeoJsonTodasRutas();
    return {...geo, 'features': _features(geo).where((f) => f['properties']['idtrufi'] == idtrufi).toList()};
  }

  @override
  Future<List<dynamic>> getUbicacionesPorTrufi(int idTrufi) async {
    if (!useGateway) return super.getUbicacionesPorTrufi(idTrufi);
    return _features(await ubicacion.paradas(rutaId: idTrufi)).map(_punto).toList();
  }

  @override
  Future<List<dynamic>> getUbicacionesTodas() async {
    if (!useGateway) return super.getUbicacionesTodas();
    return _features(await ubicacion.paradas()).map(_punto).toList();
  }

  @override
  Future<List<dynamic>> getReferenciasDestrufi(int idTrufi) async =>
      useGateway ? <dynamic>[] : await super.getReferenciasDestrufi(idTrufi);

  @override
  Future<List<dynamic>> getReferenciasDeRadiotaxi(int idRadiotaxi) async =>
      useGateway ? <dynamic>[] : await super.getReferenciasDeRadiotaxi(idRadiotaxi);

  @override
  Future<Map<String, dynamic>> getTrufiHorario(int idtrufi) async {
    if (!useGateway) return super.getTrufiHorario(idtrufi);
    final rutas = _features(await getGeoJsonPorTrufi(idtrufi));
    final horario = rutas.isEmpty ? null : rutas.first['properties']['horario'];
    return horario is Map ? Map<String, dynamic>.from(horario) : {'horario': horario};
  }
}

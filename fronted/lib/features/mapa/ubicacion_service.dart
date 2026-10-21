import '../../core/network/gateway_client.dart';

/// Cliente de la API de Ubicación. Devuelve GeoJSON tal cual para pasarlo directo a MapLibre.
class UbicacionService {
  final GatewayClient _client;

  UbicacionService(this._client);

  Future<Map<String, dynamic>> rutas({String? linea}) async => Map<String, dynamic>.from(
        await _client.get('/api/ubicacion/rutas', query: linea == null ? null : {'linea': linea}) as Map,
      );

  Future<Map<String, dynamic>> paradas({int? rutaId}) async => Map<String, dynamic>.from(
        await _client.get('/api/ubicacion/paradas', query: rutaId == null ? null : {'ruta_id': '$rutaId'}) as Map,
      );
}

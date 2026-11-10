import 'dart:convert';

import 'package:flutter/services.dart' show rootBundle;
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// Gateway simulado con respuestas que siguen los contratos de docs/openapi/.
/// Permite trabajar la UI sin backend: --dart-define=USE_MOCKS=true
http.Client crearGatewayMock() {
  final suscripciones = <String>{};
  return MockClient((req) async {
    const json = {'content-type': 'application/json; charset=utf-8'};
    if (req.method == 'DELETE' && req.url.path.startsWith('/api/notificaciones/suscripciones/')) {
      final borrada = suscripciones.remove(req.url.pathSegments.last);
      return borrada ? http.Response('', 204) : http.Response(jsonEncode({'message': 'No está suscrito a esa línea'}), 404, headers: json);
    }
    switch ('${req.method} ${req.url.path}') {
      case 'POST /api/usuarios/login':
        final body = jsonDecode(req.body) as Map<String, dynamic>;
        if (body['password'] != 'demo12345') {
          return http.Response(jsonEncode({'message': 'Credenciales incorrectas'}), 401, headers: json);
        }
        return http.Response(
          jsonEncode({
            'token_type': 'Bearer',
            'access_token': 'mock.jwt.token',
            'expires_in': 3600,
            'refresh_token': 'mock-refresh',
            'usuario': {'id': 2, 'name': 'Usuario Demo', 'email': body['email'], 'rol': 'usuario', 'activo': true},
          }),
          200,
          headers: json,
        );
      case 'GET /api/ubicacion/rutas':
        final data = jsonDecode(await rootBundle.loadString('assets/mocks/ubicacion_rutas.json')) as Map<String, dynamic>;
        final linea = req.url.queryParameters['linea'];
        if (linea != null) {
          data['features'] = (data['features'] as List).where((f) => f['properties']['linea'] == linea).toList();
        }
        return http.Response(jsonEncode(data), 200, headers: json);
      case 'GET /api/ubicacion/paradas':
        final data = jsonDecode(await rootBundle.loadString('assets/mocks/ubicacion_paradas.json')) as Map<String, dynamic>;
        final rutaId = int.tryParse(req.url.queryParameters['ruta_id'] ?? '');
        if (rutaId != null) {
          data['features'] = (data['features'] as List).where((f) => (f['properties']['rutas'] as List).any((r) => r['id'] == rutaId)).toList();
        }
        return http.Response(jsonEncode(data), 200, headers: json);
      case 'GET /api/ubicacion/radiotaxis':
        return http.Response(jsonEncode(_radiotaxisDemo), 200, headers: json);
      case 'GET /api/notificaciones':
        return http.Response(jsonEncode({'data': [], 'current_page': 1, 'last_page': 1, 'total': 0}), 200, headers: json);
      case 'GET /api/notificaciones/suscripciones':
        return http.Response(
          jsonEncode([for (final linea in suscripciones) {'id': linea.hashCode, 'linea': linea, 'created_at': '2026-11-02T12:00:00Z'}]),
          200,
          headers: json,
        );
      case 'POST /api/notificaciones/suscripciones':
        final linea = (jsonDecode(req.body) as Map<String, dynamic>)['linea'] as String;
        final nueva = suscripciones.add(linea);
        return http.Response(jsonEncode({'id': linea.hashCode, 'linea': linea, 'created_at': '2026-11-02T12:00:00Z'}), nueva ? 201 : 200, headers: json);
      case 'POST /api/notificaciones/dispositivos':
        return http.Response(jsonEncode({'id': 1, 'plataforma': 'android', 'created_at': '2026-11-02T12:00:00Z'}), 201, headers: json);
      case 'DELETE /api/notificaciones/dispositivos':
      case 'DELETE /api/usuarios/sesion':
        return http.Response('', 204);
      default:
        return http.Response(jsonEncode({'message': 'Mock sin respuesta para ${req.url.path}'}), 404, headers: json);
    }
  });
}

const _radiotaxisDemo = {
  'type': 'FeatureCollection',
  'features': [
    {
      'type': 'Feature',
      'id': 1,
      'geometry': {'type': 'Point', 'coordinates': [-66.2405, -17.3895]},
      'properties': {'id': 1, 'nombre': 'Radio Taxi Demo 1', 'sindicato': 'Datos de demostración', 'telefono': null, 'disponible': true, 'origen': 'demo', 'posicion_actualizada_en': '2026-11-02T12:00:00+00:00'},
    },
    {
      'type': 'Feature',
      'id': 2,
      'geometry': {'type': 'Point', 'coordinates': [-66.2510, -17.3930]},
      'properties': {'id': 2, 'nombre': 'Radio Taxi Demo 2', 'sindicato': 'Datos de demostración', 'telefono': null, 'disponible': true, 'origen': 'demo', 'posicion_actualizada_en': '2026-11-02T12:00:00+00:00'},
    },
  ],
};

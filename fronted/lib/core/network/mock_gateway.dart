import 'dart:convert';

import 'package:flutter/services.dart' show rootBundle;
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// Gateway simulado con respuestas que siguen los contratos de docs/openapi/.
/// Permite trabajar la UI sin backend: --dart-define=USE_MOCKS=true
http.Client crearGatewayMock() {
  return MockClient((req) async {
    const json = {'content-type': 'application/json; charset=utf-8'};
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
        return http.Response(await rootBundle.loadString('assets/mocks/ubicacion_rutas.json'), 200, headers: json);
      case 'GET /api/ubicacion/paradas':
        return http.Response(await rootBundle.loadString('assets/mocks/ubicacion_paradas.json'), 200, headers: json);
      default:
        return http.Response(jsonEncode({'message': 'Mock sin respuesta para ${req.url.path}'}), 404, headers: json);
    }
  });
}

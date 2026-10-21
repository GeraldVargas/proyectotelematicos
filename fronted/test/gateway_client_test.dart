import 'dart:convert';

import 'package:colcatrufis/core/auth/auth_service.dart';
import 'package:colcatrufis/core/auth/token_storage.dart';
import 'package:colcatrufis/core/network/gateway_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object cuerpo, int status) =>
    http.Response(jsonEncode(cuerpo), status, headers: {'content-type': 'application/json'});

void main() {
  group('GatewayClient (interceptor JWT)', () {
    test('envía el Bearer guardado', () async {
      final tokens = MemoryTokenStorage();
      await tokens.guardar(accessToken: 'abc', refreshToken: 'r1');
      String? auth;
      final client = GatewayClient(
        tokens: tokens,
        baseUrl: 'http://gw',
        httpClient: MockClient((req) async {
          auth = req.headers['Authorization'];
          return _json({'type': 'FeatureCollection', 'features': []}, 200);
        }),
      );

      await client.get('/api/ubicacion/rutas');

      expect(auth, 'Bearer abc');
    });

    test('ante 401 renueva con el refresh token y reintenta una vez', () async {
      final tokens = MemoryTokenStorage();
      await tokens.guardar(accessToken: 'vencido', refreshToken: 'r1');
      final llamadas = <String>[];
      final client = GatewayClient(
        tokens: tokens,
        baseUrl: 'http://gw',
        httpClient: MockClient((req) async {
          llamadas.add('${req.url.path} ${req.headers['Authorization'] ?? '-'}');
          if (req.url.path == '/api/usuarios/refresh') {
            expect(jsonDecode(req.body), {'refresh_token': 'r1'});
            return _json({'access_token': 'nuevo', 'refresh_token': 'r2'}, 200);
          }
          return req.headers['Authorization'] == 'Bearer nuevo'
              ? _json({'ok': true}, 200)
              : _json({'message': 'Token expirado'}, 401);
        }),
      );

      expect(await client.get('/api/usuarios/perfil'), {'ok': true});
      expect(llamadas, [
        '/api/usuarios/perfil Bearer vencido',
        '/api/usuarios/refresh -',
        '/api/usuarios/perfil Bearer nuevo',
      ]);
      expect(await tokens.leerRefreshToken(), 'r2');
    });

    test('si el refresh falla borra la sesión y avisa', () async {
      final tokens = MemoryTokenStorage();
      await tokens.guardar(accessToken: 'vencido', refreshToken: 'r1');
      final client = GatewayClient(
        tokens: tokens,
        baseUrl: 'http://gw',
        httpClient: MockClient((_) async => _json({'message': 'no'}, 401)),
      );
      final aviso = expectLater(client.sesionExpirada, emits(null));

      await expectLater(
        client.get('/api/ubicacion/rutas'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'statusCode', 401)),
      );
      await aviso;
      expect(await tokens.leerAccessToken(), isNull);
    });
  });

  test('AuthService.login guarda los tokens del Gateway', () async {
    final tokens = MemoryTokenStorage();
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://gw',
      httpClient: MockClient((req) async {
        expect(req.url.path, '/api/usuarios/login');
        expect(req.headers.containsKey('Authorization'), isFalse);
        return _json({
          'access_token': 'jwt',
          'refresh_token': 'r',
          'usuario': {'id': 2, 'name': 'Demo', 'email': 'demo@colcatrufis.local', 'rol': 'usuario'},
        }, 200);
      }),
    );

    final usuario = await AuthService(client).login('demo@colcatrufis.local', 'demo12345');

    expect(usuario.rol, 'usuario');
    expect(await tokens.leerAccessToken(), 'jwt');
  });
}

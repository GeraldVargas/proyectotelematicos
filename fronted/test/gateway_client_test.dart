import 'dart:convert';

import 'package:colcatrufis/core/auth/auth_service.dart';
import 'package:colcatrufis/core/auth/token_storage.dart';
import 'package:colcatrufis/core/network/gateway_client.dart';
import 'package:colcatrufis/features/mapa/ubicacion_service.dart';
import 'package:colcatrufis/features/notificaciones/notificaciones_service.dart';
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

  test('AuthService.logout revoca el refresh token y borra la sesión', () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'jwt', refreshToken: 'r1');
    late http.BaseRequest pedida;
    late String cuerpo;
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://gw',
      httpClient: MockClient((req) async {
        pedida = req;
        cuerpo = req.body;
        return http.Response('', 204);
      }),
    );

    await AuthService(client).logout();

    expect('${pedida.method} ${pedida.url.path}', 'DELETE /api/usuarios/sesion');
    expect(jsonDecode(cuerpo), {'refresh_token': 'r1'});
    expect(await tokens.leerAccessToken(), isNull);
  });

  test('AuthService.logout cierra la sesión local aunque el servidor falle', () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'jwt', refreshToken: 'r1');
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://gw',
      httpClient: MockClient((_) async => throw Exception('sin red')),
    );

    await AuthService(client).logout();

    expect(await tokens.leerRefreshToken(), isNull);
  });

  test('UbicacionService.radiotaxis devuelve el GeoJSON', () async {
    final client = GatewayClient(
      tokens: MemoryTokenStorage(),
      baseUrl: 'http://gw',
      httpClient: MockClient((req) async {
        expect(req.url.path, '/api/ubicacion/radiotaxis');
        return _json({'type': 'FeatureCollection', 'features': []}, 200);
      }),
    );

    expect((await UbicacionService(client).radiotaxis())['type'], 'FeatureCollection');
  });

  group('NotificacionesService', () {
    late List<String> llamadas;
    late NotificacionesService servicio;

    setUp(() {
      llamadas = [];
      servicio = NotificacionesService(GatewayClient(
        tokens: MemoryTokenStorage(),
        baseUrl: 'http://gw',
        httpClient: MockClient((req) async {
          llamadas.add('${req.method} ${req.url.path} ${req.body}');
          if (req.method == 'GET') {
            return _json([
              {'id': 1, 'linea': '252', 'created_at': '2026-11-02T12:00:00Z'}
            ], 200);
          }
          return req.method == 'DELETE' ? http.Response('', 204) : _json({'id': 1}, 201);
        }),
      ));
    });

    test('lista las líneas suscritas', () async {
      expect(await servicio.lineasSuscritas(), {'252'});
    });

    test('suscribe y desuscribe por número de línea', () async {
      await servicio.suscribir('201');
      await servicio.desuscribir('201');

      expect(llamadas, [
        'POST /api/notificaciones/suscripciones {"linea":"201"}',
        'DELETE /api/notificaciones/suscripciones/201 ',
      ]);
    });

    test('registra y elimina el token del dispositivo', () async {
      await servicio.registrarDispositivo('token-fcm', 'android');
      await servicio.eliminarDispositivo('token-fcm');

      expect(llamadas, [
        'POST /api/notificaciones/dispositivos {"token":"token-fcm","plataforma":"android"}',
        'DELETE /api/notificaciones/dispositivos {"token":"token-fcm"}',
      ]);
    });
  });
}

import 'dart:async';
import 'dart:convert';

import 'package:colcatrufis/core/auth/auth_service.dart';
import 'package:colcatrufis/core/auth/token_storage.dart';
import 'package:colcatrufis/core/network/gateway_client.dart';
import 'package:colcatrufis/core/network/mock_gateway.dart';
import 'package:colcatrufis/features/mapa/ubicacion_service.dart';
import 'package:colcatrufis/features/notificaciones/notificaciones_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, int status) => http.Response(
      jsonEncode(body),
      status,
      headers: {'content-type': 'application/json'},
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('sesión mock permite consultar, activar y quitar avisos y salir',
      () async {
    final tokens = MemoryTokenStorage();
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: crearGatewayMock(),
    );
    addTearDown(client.dispose);
    final auth = AuthService(client);
    final ubicacion = UbicacionService(client);
    final avisos = NotificacionesService(client);

    expect(await auth.haySesion(), isFalse);
    await auth.login('demo@colcatrufis.local', 'demo12345');
    expect(await auth.haySesion(), isTrue);
    expect((await ubicacion.rutas(linea: '252'))['features'], hasLength(1));
    expect((await ubicacion.radiotaxis())['features'], isNotEmpty);
    expect(await avisos.lineasSuscritas(), isEmpty);

    await avisos.suscribir('252');
    await avisos.suscribir('201');
    expect(await avisos.lineasSuscritas(), {'252', '201'});
    await avisos.desuscribir('252');
    expect(await avisos.lineasSuscritas(), {'201'});

    await auth.logout();
    expect(await auth.haySesion(), isFalse);
    expect(await tokens.leerRefreshToken(), isNull);
  });

  test('dos consultas con 401 comparten una sola renovación de sesión',
      () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'vencido', refreshToken: 'r1');
    final recibidas = Completer<void>();
    var rechazadas = 0;
    var renovaciones = 0;
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: MockClient((request) async {
        if (request.url.path == '/api/usuarios/refresh') {
          renovaciones++;
          await recibidas.future;
          return _json({'access_token': 'nuevo', 'refresh_token': 'r2'}, 200);
        }
        if (request.headers['Authorization'] == 'Bearer vencido') {
          rechazadas++;
          if (rechazadas == 2) recibidas.complete();
          return _json({'message': 'Token expirado'}, 401);
        }
        expect(request.headers['Authorization'], 'Bearer nuevo');
        return _json({'ok': true}, 200);
      }),
    );
    addTearDown(client.dispose);

    final respuestas = await Future.wait([
      client.get('/api/ubicacion/rutas'),
      client.get('/api/notificaciones'),
    ]);

    expect(respuestas, [
      {'ok': true},
      {'ok': true},
    ]);
    expect(renovaciones, 1);
    expect(await tokens.leerRefreshToken(), 'r2');
  });

  test('un JWT renovado rechazado termina la sesión sin repetir refresh',
      () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'vencido', refreshToken: 'r1');
    var renovaciones = 0;
    var consultas = 0;
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: MockClient((request) async {
        if (request.url.path == '/api/usuarios/refresh') {
          renovaciones++;
          return _json({'access_token': 'nuevo', 'refresh_token': 'r2'}, 200);
        }
        consultas++;
        return _json({'message': 'Token no aceptado'}, 401);
      }),
    );
    addTearDown(client.dispose);
    final expiracion = expectLater(client.sesionExpirada, emits(null));

    await expectLater(
      client.get('/api/ubicacion/rutas'),
      throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 401)),
    );
    await expiracion;

    expect(renovaciones, 1);
    expect(consultas, 2);
    expect(await tokens.leerAccessToken(), isNull);
    expect(await tokens.leerRefreshToken(), isNull);
  });

  test('logout revoca también el refresh que se rota durante el cierre',
      () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'vencido', refreshToken: 'r1');
    var refreshVigente = 'r1';
    var revocado = false;
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: MockClient((request) async {
        if (request.url.path == '/api/usuarios/refresh') {
          expect(jsonDecode(request.body), {'refresh_token': 'r1'});
          refreshVigente = 'r2';
          return _json({'access_token': 'nuevo', 'refresh_token': 'r2'}, 200);
        }
        expect(request.method, 'DELETE');
        expect(request.url.path, '/api/usuarios/sesion');
        if (request.headers['Authorization'] == 'Bearer vencido') {
          return _json({'message': 'Token expirado'}, 401);
        }
        if ((jsonDecode(request.body) as Map)['refresh_token'] ==
            refreshVigente) {
          revocado = true;
        }
        return http.Response('', 204);
      }),
    );
    addTearDown(client.dispose);

    await AuthService(client).logout();

    expect(revocado, isTrue,
        reason: 'El refresh r2 no debe quedar activo en el servidor');
    expect(await tokens.leerAccessToken(), isNull);
    expect(await tokens.leerRefreshToken(), isNull);
  });

  test(
      'fallo de suscripciones conserva sesión y propaga el mensaje del servidor',
      () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'jwt', refreshToken: 'r1');
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: MockClient((_) async =>
          _json({'message': 'Servicio temporalmente no disponible'}, 503)),
    );
    addTearDown(client.dispose);

    await expectLater(
      NotificacionesService(client).suscribir('252'),
      throwsA(isA<ApiException>()
          .having((e) => e.statusCode, 'status', 503)
          .having((e) => e.mensaje, 'mensaje',
              'Servicio temporalmente no disponible')),
    );

    expect(await tokens.leerAccessToken(), 'jwt');
    expect(await tokens.leerRefreshToken(), 'r1');
  });

  test('logout cierra ambos tokens locales si el servidor responde 503',
      () async {
    final tokens = MemoryTokenStorage();
    await tokens.guardar(accessToken: 'jwt', refreshToken: 'r1');
    final client = GatewayClient(
      tokens: tokens,
      baseUrl: 'http://mock',
      httpClient: MockClient(
          (_) async => _json({'message': 'Servicio no disponible'}, 503)),
    );
    addTearDown(client.dispose);

    await AuthService(client).logout();

    expect(await tokens.leerAccessToken(), isNull);
    expect(await tokens.leerRefreshToken(), isNull);
  });
}

import 'dart:convert';

import 'package:colcatrufis/core/network/mock_gateway.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('fixtures reales contienen 22 rutas, 135 paradas y relaciones coherentes', () async {
    final client = crearGatewayMock();
    addTearDown(client.close);
    final rutas = jsonDecode((await client.get(Uri.parse('http://mock/api/ubicacion/rutas'))).body);
    final paradas = jsonDecode((await client.get(Uri.parse('http://mock/api/ubicacion/paradas'))).body);
    expect(rutas['type'], 'FeatureCollection');
    expect(rutas['features'], hasLength(22));
    expect(paradas['features'], hasLength(135));
    final ids = (rutas['features'] as List).map((f) => f['id']).toSet();
    for (final parada in paradas['features']) {
      expect(parada['geometry']['type'], 'Point');
      expect(parada['properties']['rutas'], isNotEmpty);
      for (final ruta in parada['properties']['rutas']) {
        expect(ids, contains(ruta['id']));
      }
    }
  });

  test('mocks aplican filtros de linea y paradas de la ruta', () async {
    final client = crearGatewayMock();
    addTearDown(client.close);
    final rutas = jsonDecode((await client.get(Uri.parse('http://mock/api/ubicacion/rutas?linea=252'))).body);
    expect(rutas['features'], hasLength(1));
    final id = rutas['features'][0]['id'];
    final paradas = jsonDecode((await client.get(Uri.parse('http://mock/api/ubicacion/paradas?ruta_id=$id'))).body);
    expect(paradas['features'], isNotEmpty);
    for (final parada in paradas['features']) {
      expect((parada['properties']['rutas'] as List).any((r) => r['id'] == id), isTrue);
    }
  });

  test('mock de radio taxis devuelve puntos GeoJSON', () async {
    final client = crearGatewayMock();
    addTearDown(client.close);
    final taxis = jsonDecode((await client.get(Uri.parse('http://mock/api/ubicacion/radiotaxis'))).body);
    expect(taxis['type'], 'FeatureCollection');
    expect(taxis['features'], isNotEmpty);
    expect(taxis['features'][0]['geometry']['type'], 'Point');
  });

  test('mock de suscripciones agrega y elimina líneas', () async {
    final client = crearGatewayMock();
    addTearDown(client.close);
    final url = Uri.parse('http://mock/api/notificaciones/suscripciones');
    expect((await client.post(url, body: jsonEncode({'linea': '252'}))).statusCode, 201);
    expect((await client.post(url, body: jsonEncode({'linea': '252'}))).statusCode, 200);
    expect(jsonDecode((await client.get(url)).body), hasLength(1));
    expect((await client.delete(Uri.parse('$url/252'))).statusCode, 204);
    expect((await client.delete(Uri.parse('$url/252'))).statusCode, 404);
  });
}

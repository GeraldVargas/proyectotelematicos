import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:colcatrufis/core/auth/token_storage.dart';
import 'package:colcatrufis/core/network/gateway_client.dart';
import 'package:colcatrufis/core/network/mock_gateway.dart';
import 'package:colcatrufis/features/mapa/ubicacion_service.dart';
import 'package:colcatrufis/services/classic_transport_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late ClassicTransportService service;
  setUp(() {
    final client = GatewayClient(tokens: MemoryTokenStorage(),
      httpClient: crearGatewayMock());
    addTearDown(client.dispose);
    service = ClassicTransportService(baseUrl: 'http://unused',
      ubicacion: UbicacionService(client), useGateway: true);
  });

  test('Actualizar datos refleja ediciones y horario textual del administrador', () async {
    var nombre = 'Antes';
    final client = GatewayClient(tokens: MemoryTokenStorage(), httpClient: MockClient((_) async =>
      http.Response(jsonEncode({'type': 'FeatureCollection', 'features': [{
        'id': 1, 'properties': {'id': 1, 'linea': '201', 'nombre': nombre,
          'sindicato': 'Central', 'horario': '06:00–22:00', 'tarifa': 2.5, 'frecuencia_min': 10},
        'geometry': {'type': 'LineString', 'coordinates': [[-66.24, -17.38], [-66.23, -17.39]]}
      }]}), 200, headers: {'content-type': 'application/json; charset=utf-8'})));
    addTearDown(client.dispose);
    final api = ClassicTransportService(baseUrl: 'http://unused', ubicacion: UbicacionService(client), useGateway: true);
    expect((await api.getTrufis()).first['nombre'], 'Antes');
    nombre = 'Después';
    api.invalidarCatalogo();
    final trufi = (await api.getTrufis()).first;
    expect(trufi['nombre'], 'Después');
    expect(trufi['tarifa'], 2.5);
    expect(trufi['frecuencia_min'], 10);
    expect((await api.getTrufiHorario(1))['horario'], '06:00–22:00');
  });

  test('el catálogo Gateway alimenta listas y rutas clásicas con los mismos IDs', () async {
    final trufis = await service.getTrufis();
    final sindicatos = await service.getSindicatos();
    final geo = await service.getGeoJsonTodasRutas();
    expect(trufis, hasLength(22));
    expect(sindicatos.expand((s) => s['trufis'] as List), hasLength(22));
    for (final t in trufis) {
      final ruta = await service.getGeoJsonPorTrufi(t['idtrufi'] as int);
      expect(ruta['features'], hasLength(1));
      expect(ruta['features'][0]['properties']['linea'], t['nom_linea']);
    }
    expect(geo['features'], hasLength(22));
  });

  test('las paradas conservan coordenadas GeoJSON y el filtro de ruta', () async {
    final todas = await service.getUbicacionesTodas();
    final paradas = await service.getUbicacionesPorTrufi(1);
    expect(todas, isNotEmpty);
    expect(paradas, isNotEmpty);
    for (final p in paradas) {
      expect(p['latitud'], inInclusiveRange(-18, -17));
      expect(p['longitud'], inInclusiveRange(-67, -66));
      expect((p['rutas'] as List).any((r) => r['id'] == 1), isTrue);
    }
  });

  test('radiotaxis usan los campos clásicos sin consultar detalles del monolito', () async {
    final taxis = await service.getRadioTaxis();
    expect(taxis, hasLength(2));
    expect(taxis.first['nombre_comercial'], 'Radio Taxi Demo 1');
    expect(taxis.first['latitud'], -17.3895);
    expect(taxis.first['longitud'], -66.2405);
    expect(await service.getReferenciasDeRadiotaxi(1), isEmpty);
    expect(await service.getReferenciasDestrufi(1), isEmpty);
  });
}

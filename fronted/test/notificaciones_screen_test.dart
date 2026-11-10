import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:colcatrufis/core/auth/token_storage.dart';
import 'package:colcatrufis/core/network/gateway_client.dart';
import 'package:colcatrufis/features/mapa/ubicacion_service.dart';
import 'package:colcatrufis/features/notificaciones/notificaciones_service.dart';
import 'package:colcatrufis/features/notificaciones/suscripciones_screen.dart';

void main() {
  testWidgets('muestra cambios, carga avisos anteriores y permite seguir una línea', (tester) async {
    final paginas = <String>[];
    final suscripciones = <String>[];
    final client = GatewayClient(tokens: MemoryTokenStorage(), httpClient: MockClient((r) async {
      Object datos;
      if (r.url.path == '/api/notificaciones') {
        final pagina = r.url.queryParameters['page']!;
        paginas.add(pagina);
        datos = {'data': [{'titulo': 'Cambio en la línea 252', 'mensaje': pagina == '1' ? 'Desvío por obras' : 'Horario anterior', 'created_at': '2026-11-10T12:00:00Z'}], 'last_page': 2};
      } else if (r.url.path == '/api/ubicacion/rutas') {
        datos = {'features': [{'properties': {'linea': '252', 'nombre': 'Ruta centro', 'sindicato': 'Central'}}]};
      } else if (r.method == 'POST') {
        suscripciones.add(jsonDecode(r.body)['linea'] as String);
        datos = {};
      } else { datos = []; }
      return http.Response(jsonEncode(datos), 200, headers: {'content-type': 'application/json; charset=utf-8'});
    }));
    addTearDown(client.dispose);
    await tester.pumpWidget(MaterialApp(home: SuscripcionesScreen(
      notificaciones: NotificacionesService(client), ubicacion: UbicacionService(client))));
    await tester.pumpAndSettle();
    expect(find.text('Desvío por obras'), findsOneWidget);
    await tester.tap(find.text('Ver avisos anteriores'));
    await tester.pumpAndSettle();
    expect(paginas, ['1', '2']);
    expect(find.text('Horario anterior'), findsOneWidget);
    await tester.tap(find.text('Mis líneas'));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(suscripciones, ['252']);
    expect(find.text('1 línea activada'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('sin avisos explica cómo activarlos y refresca sin push', (tester) async {
    var consultas = 0;
    final client = GatewayClient(tokens: MemoryTokenStorage(), httpClient: MockClient((r) async {
      Object datos;
      if (r.url.path == '/api/notificaciones') {
        consultas++;
        datos = {'data': consultas == 1 ? [] : [{'titulo': 'Nuevo cambio', 'mensaje': 'Nueva tarifa'}], 'last_page': 1};
      } else if (r.url.path == '/api/ubicacion/rutas') { datos = {'features': []}; }
      else { datos = []; }
      return http.Response(jsonEncode(datos), 200, headers: {'content-type': 'application/json'});
    }));
    addTearDown(client.dispose);
    await tester.pumpWidget(MaterialApp(home: SuscripcionesScreen(
      notificaciones: NotificacionesService(client), ubicacion: UbicacionService(client))));
    await tester.pumpAndSettle();
    expect(find.text('Todavía no tienes avisos'), findsOneWidget);
    await tester.pump(const Duration(seconds: 30));
    await tester.pumpAndSettle();
    expect(find.text('Nueva tarifa'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
}

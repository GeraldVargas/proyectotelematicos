import '../../core/network/gateway_client.dart';

/// Cliente de la API de Notificaciones: suscripciones por línea y dispositivos para push.
class NotificacionesService {
  final GatewayClient _client;

  NotificacionesService(this._client);

  Future<Map<String, dynamic>> avisos({int pagina = 1}) async =>
      Map<String, dynamic>.from(await _client.get('/api/notificaciones',
        query: {'page': '$pagina', 'por_pagina': '20'}) as Map);

  Future<Set<String>> lineasSuscritas() async {
    final datos = await _client.get('/api/notificaciones/suscripciones') as List;
    return datos.map((s) => (s as Map)['linea'] as String).toSet();
  }

  Future<void> suscribir(String linea) => _client.post('/api/notificaciones/suscripciones', {'linea': linea});

  Future<void> desuscribir(String linea) =>
      _client.delete('/api/notificaciones/suscripciones/${Uri.encodeComponent(linea)}');

  Future<void> registrarDispositivo(String token, String plataforma) =>
      _client.post('/api/notificaciones/dispositivos', {'token': token, 'plataforma': plataforma});

  Future<void> eliminarDispositivo(String token) =>
      _client.delete('/api/notificaciones/dispositivos', cuerpo: {'token': token});
}

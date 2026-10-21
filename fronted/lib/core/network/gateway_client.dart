import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../auth/token_storage.dart';
import 'gateway_config.dart';

class ApiException implements Exception {
  final int? statusCode;
  final String mensaje;

  ApiException(this.mensaje, {this.statusCode});

  @override
  String toString() => mensaje;
}

/// Único punto de salida HTTP hacia el API Gateway.
///
/// Hace de interceptor: agrega `Authorization: Bearer <JWT>` y, si el Gateway responde 401,
/// intenta renovar el token con /api/usuarios/refresh una sola vez. Si tampoco funciona,
/// borra la sesión y emite en [sesionExpirada] para que la UI vuelva al login.
class GatewayClient {
  final String baseUrl;
  final http.Client _http;
  final TokenStorage tokens;
  final _sesionExpirada = StreamController<void>.broadcast();
  Future<bool>? _renovando;

  GatewayClient({
    required this.tokens,
    http.Client? httpClient,
    this.baseUrl = GatewayConfig.baseUrl,
  }) : _http = httpClient ?? http.Client();

  Stream<void> get sesionExpirada => _sesionExpirada.stream;

  Future<dynamic> get(String ruta, {Map<String, String>? query}) =>
      _enviar('GET', ruta, query: query);

  Future<dynamic> post(String ruta, Map<String, dynamic> cuerpo, {bool autenticado = true}) =>
      _enviar('POST', ruta, cuerpo: cuerpo, autenticado: autenticado);

  Future<dynamic> _enviar(
    String metodo,
    String ruta, {
    Map<String, dynamic>? cuerpo,
    Map<String, String>? query,
    bool autenticado = true,
    bool reintento = true,
  }) async {
    final uri = Uri.parse('$baseUrl$ruta').replace(queryParameters: query);
    final headers = <String, String>{
      'Accept': 'application/json',
      if (cuerpo != null) 'Content-Type': 'application/json',
    };
    if (autenticado) {
      final token = await tokens.leerAccessToken();
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }

    final http.Response res;
    try {
      final req = http.Request(metodo, uri)..headers.addAll(headers);
      if (cuerpo != null) req.body = jsonEncode(cuerpo);
      res = await http.Response.fromStream(await _http.send(req).timeout(GatewayConfig.timeout));
    } on TimeoutException {
      throw ApiException('El servidor tardó demasiado en responder');
    } catch (_) {
      throw ApiException('No se pudo conectar con el servidor');
    }

    if (res.statusCode == 401 && autenticado) {
      if (reintento && await _renovar()) {
        return _enviar(metodo, ruta, cuerpo: cuerpo, query: query, reintento: false);
      }
      await tokens.borrar();
      _sesionExpirada.add(null);
      throw ApiException('Tu sesión expiró. Vuelve a iniciar sesión.', statusCode: 401);
    }

    final dynamic json = res.body.isEmpty ? null : _decodificar(res.body);

    if (res.statusCode < 200 || res.statusCode >= 300) {
      final mensaje = json is Map && json['message'] is String ? json['message'] as String : 'Error ${res.statusCode}';
      throw ApiException(mensaje, statusCode: res.statusCode);
    }
    return json;
  }

  /// Evita que varias peticiones con 401 simultáneas disparen varios refresh.
  Future<bool> _renovar() => _renovando ??= _pedirRefresh().whenComplete(() => _renovando = null);

  Future<bool> _pedirRefresh() async {
    final refresh = await tokens.leerRefreshToken();
    if (refresh == null) return false;
    try {
      final datos = await _enviar('POST', '/api/usuarios/refresh',
          cuerpo: {'refresh_token': refresh}, autenticado: false);
      await tokens.guardar(
        accessToken: datos['access_token'] as String,
        refreshToken: datos['refresh_token'] as String,
      );
      return true;
    } on ApiException {
      return false;
    }
  }

  dynamic _decodificar(String body) {
    try {
      return jsonDecode(body);
    } on FormatException {
      return null;
    }
  }

  void dispose() {
    _sesionExpirada.close();
    _http.close();
  }
}

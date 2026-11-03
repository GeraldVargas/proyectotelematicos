import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';

class ApiService {
  final String baseUrl;

  ApiService({required this.baseUrl});

  Uri _u(String path) => Uri.parse('$baseUrl$path');

  // Respuestas y coordenadas pueden contener datos de usuarios; nunca se escriben en producción.
  void _debug(String mensaje) {
    if (kDebugMode) debugPrint(mensaje);
  }

  Future<dynamic> _get(String path) async {
    try {
      final res = await http
          .get(
            _u(path),
            headers: const {
              'Accept': 'application/json',
            },
          )
          .timeout(const Duration(seconds: 30));

      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('HTTP ${res.statusCode}: ${res.body}');
      }

      final body = jsonDecode(res.body);

      // Soporta:
      // 1) [ ... ]
      // 2) { "data": [ ... ] }
      // 3) { "success": true, "data": [ ... ] }

      if (body is List) return body;
      if (body is Map && body['data'] is List) return body['data'];
      if (body is Map && body['success'] == true && body['data'] is List) {
        return body['data'];
      }
      return body; // En caso de que el formato sea inesperado
    } catch (e) {
      throw Exception('Error al obtener datos: $e');
    }
  }

  // =========================
  // ENDPOINTS (según tu api.php)
  // =========================

  Future<List<dynamic>> getSindicatos() async {
    final data = await _get('/sindicatos');
    return (data as List).cast<dynamic>();
  }

  Future<List<dynamic>> getRadioTaxis() async {
    final data = await _get('/sindicato-radiotaxis');
    return (data as List).cast<dynamic>();
  }

  Future<List<dynamic>> getTrufis() async {
    final data = await _get('/trufis');
    return (data as List).cast<dynamic>();
  }

  Future<List<dynamic>> getTrufiRutas(int idtrufi) async {
    final data = await _get('/trufis/$idtrufi/rutas');
    return (data as List).cast<dynamic>();
  }

  Future<Map<String, dynamic>> getGeoJsonPorTrufi(int idtrufi) async {
    final data = await _get('/trufis/$idtrufi/rutas/geojson');
    if (data is Map<String, dynamic>) {
      return data;
    }
    throw Exception('Formato inesperado para GeoJSON');
  }
  Future<Map<String, dynamic>> getGeoJsonTodasRutas() async {
    final data = await _get('/trufis/rutas/geojson');
    if (data is Map<String, dynamic>) return data;
    throw Exception('Formato inesperado para GeoJSON Todas Rutas');
  }

  // Referencias
  Future<List<dynamic>> getReferencias() async {
    final data = await _get('/referencias');
    _debug("getReferencias(): ${data.runtimeType}");
    if (data is List) return data;
    _debug("getReferencias(): formato inesperado ${data.runtimeType}");
    return [];
  }

  Future<List<dynamic>> getReferenciasDestrufi(int idTrufi) async {
    final data = await _get('/trufis/$idTrufi/referencias');
    _debug("getReferenciasDestrufi($idTrufi): ${data.runtimeType}");
    
    // Si es paginación Laravel con 'data' adentro
    if (data is Map && data['data'] is List) {
      final referencias = data['data'] as List;
      _debug("Referencias paginadas: ${referencias.length}");
      return referencias;
    }
    
    // Si es directo List
    if (data is List) return data;
    
    _debug("Referencias de trufi: formato inesperado ${data.runtimeType}");
    return [];
  }

  Future<List<dynamic>> getReferenciasDeRadiotaxi(int idRadiotaxi) async {
    final data = await _get('/radiotaxis/$idRadiotaxi/referencias');
    _debug("getReferenciasDeRadiotaxi($idRadiotaxi): ${data.runtimeType}");
    
    // Si es paginación Laravel con 'data' adentro
    if (data is Map && data['data'] is List) {
      final referencias = data['data'] as List;
      _debug("Referencias paginadas: ${referencias.length}");
      return referencias;
    }
    
    // Si es directo List
    if (data is List) return data;
    
    _debug("Referencias de radio taxi: formato inesperado ${data.runtimeType}");
    return [];
  }

  // Ubicaciones - Con mejor manejo de errores para APK
  Future<List<dynamic>> getUbicacionesPorTrufi(int idTrufi) async {
    try {
      _debug("getUbicacionesPorTrufi($idTrufi) iniciando");

      final res = await http
          .get(
            _u('/trufis/$idTrufi/ubicaciones'),
            headers: const {
              'Accept': 'application/json',
              'User-Agent': 'ColcaTrufisApp/1.0',
            },
          )
          .timeout(Duration(seconds: AppConfig.heavyApiTimeoutSeconds));

      _debug("Ubicaciones HTTP: ${res.statusCode}");

      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('HTTP ${res.statusCode}: ${res.body}');
      }

      final body = jsonDecode(res.body);
      _debug("Ubicaciones de trufi: ${body.runtimeType}");

      if (body is List) return body;
      _debug("Ubicaciones de trufi: formato inesperado ${body.runtimeType}");
      return [];
    } catch (e) {
      _debug("Error obteniendo ubicaciones de trufi: $e");
      rethrow; // Re-lanzar el error para que el frontend lo pueda manejar
    }
  }

  Future<List<dynamic>> getUbicacionesTodas() async {
    try {
      _debug("getUbicacionesTodas() iniciando");

      final res = await http
          .get(
            _u('/ubicaciones'),
            headers: const {
              'Accept': 'application/json',
              'User-Agent': 'ColcaTrufisApp/1.0',
            },
          )
          .timeout(Duration(seconds: AppConfig.heavyApiTimeoutSeconds));

      _debug("Todas las ubicaciones HTTP: ${res.statusCode}");

      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('HTTP ${res.statusCode}: ${res.body}');
      }

      final body = jsonDecode(res.body);
      _debug("Todas las ubicaciones: ${body.runtimeType}");

      if (body is List) return body;
      _debug("Todas las ubicaciones: formato inesperado ${body.runtimeType}");
      return [];
    } catch (e) {
      _debug("Error obteniendo todas las ubicaciones: $e");
      rethrow; // Re-lanzar el error para que el frontend lo pueda manejar
    }
  }

  // Horario del trufi (hora_entrada, hora_salida)
  Future<Map<String, dynamic>> getTrufiHorario(int idtrufi) async {
    try {
      final data = await _get('/trufis/$idtrufi/horario');
      if (data is Map) return Map<String, dynamic>.from(data);
      return {};
    } catch (_) {
      return {};
    }
  }

}

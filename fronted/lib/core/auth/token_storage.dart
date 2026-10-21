import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Guarda los tokens fuera de SharedPreferences (RNF4: credenciales almacenadas de forma segura).
abstract class TokenStorage {
  Future<String?> leerAccessToken();
  Future<String?> leerRefreshToken();
  Future<void> guardar({required String accessToken, required String refreshToken});
  Future<void> borrar();
}

class SecureTokenStorage implements TokenStorage {
  static const _access = 'jwt_access_token';
  static const _refresh = 'jwt_refresh_token';

  final FlutterSecureStorage _storage;

  SecureTokenStorage([FlutterSecureStorage? storage])
      : _storage = storage ?? const FlutterSecureStorage();

  @override
  Future<String?> leerAccessToken() => _storage.read(key: _access);

  @override
  Future<String?> leerRefreshToken() => _storage.read(key: _refresh);

  @override
  Future<void> guardar({required String accessToken, required String refreshToken}) async {
    await _storage.write(key: _access, value: accessToken);
    await _storage.write(key: _refresh, value: refreshToken);
  }

  @override
  Future<void> borrar() async {
    await _storage.delete(key: _access);
    await _storage.delete(key: _refresh);
  }
}

/// Implementación en memoria para tests y para el modo mock.
class MemoryTokenStorage implements TokenStorage {
  String? _accessToken;
  String? _refreshToken;

  @override
  Future<String?> leerAccessToken() async => _accessToken;

  @override
  Future<String?> leerRefreshToken() async => _refreshToken;

  @override
  Future<void> guardar({required String accessToken, required String refreshToken}) async {
    _accessToken = accessToken;
    _refreshToken = refreshToken;
  }

  @override
  Future<void> borrar() async {
    _accessToken = null;
    _refreshToken = null;
  }
}

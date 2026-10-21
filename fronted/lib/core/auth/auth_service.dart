import '../network/gateway_client.dart';

class Usuario {
  final int id;
  final String nombre;
  final String email;
  final String rol;

  Usuario({required this.id, required this.nombre, required this.email, required this.rol});

  factory Usuario.fromJson(Map<String, dynamic> json) => Usuario(
        id: json['id'] as int,
        nombre: json['name'] as String,
        email: json['email'] as String,
        rol: json['rol'] as String,
      );
}

/// Login contra la API de Usuarios (POST /api/usuarios/login) a través del Gateway.
class AuthService {
  final GatewayClient _client;

  AuthService(this._client);

  Future<bool> haySesion() async => await _client.tokens.leerAccessToken() != null;

  Future<Usuario> login(String email, String password) async {
    final datos = await _client.post(
      '/api/usuarios/login',
      {'email': email, 'password': password},
      autenticado: false,
    );
    await _client.tokens.guardar(
      accessToken: datos['access_token'] as String,
      refreshToken: datos['refresh_token'] as String,
    );
    return Usuario.fromJson(datos['usuario'] as Map<String, dynamic>);
  }

  Future<Usuario> perfil() async =>
      Usuario.fromJson(await _client.get('/api/usuarios/perfil') as Map<String, dynamic>);

  Future<void> logout() => _client.tokens.borrar();
}

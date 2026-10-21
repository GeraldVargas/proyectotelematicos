/// Configuración del cliente hacia el API Gateway (arquitectura de microservicios).
///
/// Se define al compilar, sin tocar código:
///   flutter run --dart-define=GATEWAY_URL=http://192.168.1.50:8080
///   flutter run --dart-define=USE_MOCKS=true     (sin backend, con datos del contrato OpenAPI)
///
/// Por defecto apunta al Gateway local visto desde el emulador de Android (10.0.2.2 = PC host).
/// La app clásica (HomeScreen) sigue usando AppConfig.baseUrl del monolito.
class GatewayConfig {
  static const String baseUrl = String.fromEnvironment(
    'GATEWAY_URL',
    defaultValue: 'http://10.0.2.2:8080',
  );

  static const bool usarMocks = bool.fromEnvironment('USE_MOCKS');

  static const Duration timeout = Duration(seconds: 20);

  /// Mapa base vectorial: el cliente lo pide directo al proveedor, sin pasar por el Gateway.
  static const String estiloMapaBase = 'https://tiles.openfreemap.org/styles/liberty';
}

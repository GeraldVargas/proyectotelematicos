import 'auth/auth_service.dart';
import 'auth/token_storage.dart';
import 'network/gateway_client.dart';
import 'network/gateway_config.dart';
import 'network/mock_gateway.dart';
import '../features/mapa/ubicacion_service.dart';

/// Instancias compartidas de la capa de red nueva (Gateway + JWT).
class Servicios {
  static final GatewayClient gateway = GatewayConfig.usarMocks
      ? GatewayClient(tokens: MemoryTokenStorage(), httpClient: crearGatewayMock())
      : GatewayClient(tokens: SecureTokenStorage());

  static final AuthService auth = AuthService(gateway);
  static final UbicacionService ubicacion = UbicacionService(gateway);
}

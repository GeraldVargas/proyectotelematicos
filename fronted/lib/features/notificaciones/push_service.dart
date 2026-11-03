import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../core/network/gateway_client.dart';
import '../../core/network/gateway_config.dart';
import 'notificaciones_service.dart';

/// Notificaciones push con Firebase Cloud Messaging.
///
/// La configuración de Firebase se pasa al compilar (ver fronted/README.md). Sin ella la app
/// funciona igual y solo se omite el push; los avisos siguen disponibles en la API.
class PushService {
  /// Permite mostrar el aviso en primer plano desde cualquier pantalla.
  static final GlobalKey<ScaffoldMessengerState> mensajero =
      GlobalKey<ScaffoldMessengerState>();

  static const _apiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const _appId = String.fromEnvironment('FIREBASE_APP_ID');
  static const _senderId = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const _projectId = String.fromEnvironment('FIREBASE_PROJECT_ID');

  final NotificacionesService _notificaciones;
  StreamSubscription<RemoteMessage>? _mensajes;
  StreamSubscription<String>? _renovaciones;
  bool _iniciado = false;
  String? _token;

  PushService(this._notificaciones);

  bool get _configurado =>
      !GatewayConfig.usarMocks &&
      !kIsWeb &&
      _apiKey.isNotEmpty &&
      _appId.isNotEmpty &&
      _senderId.isNotEmpty &&
      _projectId.isNotEmpty;

  /// Pide permiso, registra el token en el backend y escucha los mensajes. Es idempotente.
  /// Un fallo nunca interrumpe la pantalla que lo llamó: el mapa debe abrirse igual.
  Future<void> iniciar() async {
    if (_iniciado || !_configurado) return;
    _iniciado = true;
    try {
      // Firebase sobrevive al cierre de sesión. Al volver a entrar reutilizamos la instancia
      // ya creada para poder registrar de nuevo el token para la nueva sesión.
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: const FirebaseOptions(
            apiKey: _apiKey,
            appId: _appId,
            messagingSenderId: _senderId,
            projectId: _projectId,
          ),
        );
      }
      final fcm = FirebaseMessaging.instance;
      final permiso = await fcm.requestPermission();
      if (permiso.authorizationStatus == AuthorizationStatus.denied) {
        _iniciado = false;
        return;
      }
      _mensajes = FirebaseMessaging.onMessage.listen(_mostrar);
      _renovaciones = fcm.onTokenRefresh.listen(_registrar);
      final token = await fcm.getToken();
      if (token != null) await _registrar(token);
    } catch (e) {
      _iniciado = false;
      debugPrint('Push no disponible: $e');
    }
  }

  /// Al cerrar sesión este teléfono deja de recibir los avisos de la cuenta.
  Future<void> detener() async {
    await _mensajes?.cancel();
    await _renovaciones?.cancel();
    _mensajes = null;
    _renovaciones = null;
    _iniciado = false;
    final token = _token;
    _token = null;
    if (token == null) return;
    try {
      await _notificaciones.eliminarDispositivo(token);
    } on ApiException catch (e) {
      debugPrint('No se pudo eliminar el dispositivo: $e');
    }
  }

  Future<void> _registrar(String token) async {
    final plataforma =
        defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
    try {
      await _notificaciones.registrarDispositivo(token, plataforma);
      _token = token;
    } on ApiException catch (e) {
      debugPrint('No se pudo registrar el dispositivo: $e');
    }
  }

  void _mostrar(RemoteMessage mensaje) {
    final aviso = mensaje.notification;
    if (aviso == null) return;
    mensajero.currentState?.showSnackBar(SnackBar(
      duration: const Duration(seconds: 6),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(aviso.title ?? 'Aviso',
              style: const TextStyle(fontWeight: FontWeight.bold)),
          if (aviso.body != null) Text(aviso.body!),
        ],
      ),
    ));
  }
}

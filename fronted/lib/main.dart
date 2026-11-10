import 'package:flutter/material.dart';
import 'package:colcatrufis/screens/splash_screen.dart';  // Asegúrate de importar la pantalla de inicio
import 'package:colcatrufis/screens/home_screen.dart';    // Asegúrate de importar la pantalla principal
import 'package:colcatrufis/features/login/login_screen.dart';
import 'package:colcatrufis/features/notificaciones/push_service.dart';
import 'package:colcatrufis/features/notificaciones/suscripciones_screen.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Colca Trufis',
      debugShowCheckedModeBanner: false,
      scaffoldMessengerKey: PushService.mensajero,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: kPrimary, primary: kPrimary, secondary: kAqua),
        useMaterial3: true,
      ),
      home: SplashScreen(), // No necesitas const aquí
      routes: {
        '/home': (context) => HomeScreen(), // Aquí tampoco es necesario const
        // Una sola interfaz para visitantes y sesiones del Gateway.
        '/login': (context) => const LoginScreen(),
        '/mapa': (context) => HomeScreen(),
        '/suscripciones': (context) => const SuscripcionesScreen(),
      },
    );
  }
}

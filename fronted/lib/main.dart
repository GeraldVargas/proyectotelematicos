import 'package:flutter/material.dart';
import 'package:colcatrufis/screens/splash_screen.dart';  // Asegúrate de importar la pantalla de inicio
import 'package:colcatrufis/screens/home_screen.dart';    // Asegúrate de importar la pantalla principal
import 'package:colcatrufis/features/login/login_screen.dart';
import 'package:colcatrufis/features/mapa/mapa_rutas_screen.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Colca Trufis',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        primarySwatch: Colors.blue,
        useMaterial3: true,
      ),
      home: SplashScreen(), // No necesitas const aquí
      routes: {
        '/home': (context) => HomeScreen(), // Aquí tampoco es necesario const
        // Arquitectura de microservicios (Sprint 1): login JWT + mapa vectorial vía Gateway
        '/login': (context) => const LoginScreen(),
        '/mapa': (context) => const MapaRutasScreen(),
      },
    );
  }
}

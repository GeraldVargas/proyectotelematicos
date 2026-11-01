# App Flutter de ColcaTrufis

Requiere Flutter instalado y el entorno Docker de la raíz encendido.

```powershell
flutter pub get
flutter run -d chrome --dart-define=GATEWAY_URL=http://localhost:8080
```

Android emulado: `flutter run --dart-define=GATEWAY_URL=http://10.0.2.2:8080`.
Android físico en la misma Wi-Fi: `flutter run --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080`.
Autoriza esa IP de desarrollo en `android/app/src/main/res/xml/network_security_config.xml`.
El teléfono debe poder alcanzar el puerto 8080 de la PC. No uses `localhost` en el teléfono.

Usuario de la app: `demo@colcatrufis.local` / `demo12345`.
Para trabajar sin las APIs: `flutter run --dart-define=USE_MOCKS=true`; usa las mismas credenciales.
El mapa base de OpenFreeMap requiere conexión a Internet incluso en modo simulado.

Las fixtures se generan con `node scripts/generate-mocks.mjs` desde la raíz. Contienen las mismas
4 rutas y 22 paradas OSM que la semilla del backend, sin usuarios reales.

```powershell
flutter test
flutter build web --dart-define=GATEWAY_URL=http://localhost:8080
```

La prueba en un Android físico y la regresión de la app clásica requieren un dispositivo y acceso
al backend histórico; no se consideran verificadas por compilar la versión web.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Lab: Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Cookbook: Useful Flutter samples](https://docs.flutter.dev/cookbook)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.

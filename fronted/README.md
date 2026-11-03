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

## Notificaciones push (FCM)

La app no incluye credenciales. Sin estas variables compila y funciona igual, solo que sin push.
Con un proyecto de Firebase (app Android `com.example.fronted`), copia los valores de la
consola de Firebase y compila así:

```powershell
flutter run --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080 `
  --dart-define=FIREBASE_API_KEY=... --dart-define=FIREBASE_APP_ID=... `
  --dart-define=FIREBASE_SENDER_ID=... --dart-define=FIREBASE_PROJECT_ID=...
```

El backend debe enviar con ese mismo proyecto: ver [ADR 0005](../docs/adr/0005-push-fcm-y-radiotaxis.md).
El contenedor `fcm-mock` solo sirve para probar el backend: un teléfono real no recibe esos mensajes.

Las fixtures se generan con `node scripts/generate-mocks.mjs` desde la raíz. Contienen las mismas
22 rutas y 135 paradas OSM que la semilla del backend, sin usuarios reales.

```powershell
flutter test
flutter build web --dart-define=GATEWAY_URL=http://localhost:8080
```

La prueba en un Android físico y la regresión de la app clásica requieren un dispositivo y acceso
al backend histórico; no se consideran verificadas por compilar la versión web.

## Verificación en Windows (03/11/2026)

Con Flutter 3.41.2 y Dart 3.11.0 se instalaron las dependencias de Firebase declaradas y se actualizó
`pubspec.lock`. Las 20 pruebas de red, sesión, mocks, radio taxis y suscripciones pasan. Incluyen
el cierre de sesión cuando el access token venció: el refresh que se acaba de renovar también se
revoca antes de borrar los tokens locales.

La compilación web con el Gateway local se completó correctamente. `flutter analyze` no encontró
errores de compilación; informa 11 advertencias de la pantalla clásica y 202 recomendaciones,
entre ellas dos avisos de obsolescencia de la API de Firebase. No se declara limpio el análisis.
La entrega push real continúa pendiente de configurar Firebase y probar un teléfono.

También se generó `build/app/outputs/flutter-apk/app-debug.apk` (Android ARM64, modo debug) con:

```powershell
flutter build apk --debug --no-pub --target-platform android-arm64 --dart-define=GATEWAY_URL=http://10.0.2.2:8080
```

Ese APK usa la dirección del host vista desde un emulador Android. Para un teléfono físico,
repite la compilación con la IP de tu PC en `GATEWAY_URL` y autoriza esa IP de desarrollo como
se indica arriba. La compilación no sustituye la prueba de login, mapa y push en el dispositivo.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Lab: Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Cookbook: Useful Flutter samples](https://docs.flutter.dev/cookbook)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.

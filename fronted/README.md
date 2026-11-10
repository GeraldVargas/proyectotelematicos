# App Flutter de ColcaTrufis

## Requisitos

- Entorno usado para verificar esta guía: Flutter 3.41.2 y Dart 3.11.0. El lock actual exige
  Dart `>=3.11.0 <4.0.0`; conserva `pubspec.lock` para repetir las dependencias.
- Docker Desktop con el entorno de la raíz levantado, salvo que uses `USE_MOCKS=true`.
- Para Android: emulador (usa `10.0.2.2`) o teléfono en la misma red Wi-Fi que la PC.
- Para compilar Android: Android SDK, sus licencias aceptadas y un JDK compatible con Gradle
  (el proyecto compila código Java/Kotlin para Java 17). Comprueba la instalación con
  `flutter doctor -v` y los destinos con `flutter devices`.

## Ejecutar

Desde `fronted/`:

```powershell
flutter pub get
flutter run -d chrome --dart-define=GATEWAY_URL=http://localhost:8080
```

Antes, desde la raíz, levanta `docker compose --profile demo up -d --build --wait --wait-timeout 300`.
Comprueba que el Gateway responda y que el panel abra en `http://localhost:8081`.

En Android emulado:

```powershell
flutter run --dart-define=GATEWAY_URL=http://10.0.2.2:8080
```

En un teléfono físico, reemplaza `IP-DE-TU-PC` por la IP LAN de la PC:

```powershell
flutter run --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080
```

No uses `localhost` en un teléfono. Las compilaciones debug permiten HTTP local; release requiere
HTTPS. Usuario de demo: `demo@colcatrufis.local` / `demo12345`.
Al cambiar un `--dart-define`, detén y vuelve a ejecutar o compilar la app; hot reload no cambia
estos valores. La opción «Continuar sin iniciar sesión» abre la app clásica y usa el monolito
configurado en `lib/config/app_config.dart`; `GATEWAY_URL` solo cambia el cliente de microservicios.

Para trabajar sin backend:

```powershell
flutter run --dart-define=USE_MOCKS=true
```

El mapa base de OpenFreeMap necesita Internet incluso con mocks. Las fixtures incluyen las mismas
22 rutas y 135 paradas OSM del backend; el modo mock no recibe eventos de Docker ni push real.

## Variables de compilación

| Variable | Valor predeterminado / uso |
|---|---|
| `GATEWAY_URL` | `http://10.0.2.2:8080`, sin `/api` al final; usa localhost en Chrome o la IP LAN en teléfono |
| `USE_MOCKS` | `false`; `true` usa fixtures locales y sesión de prueba |
| `FIREBASE_API_KEY` | Vacío; API key de la aplicación Android registrada en Firebase |
| `FIREBASE_APP_ID` | Vacío; App ID Android de Firebase |
| `FIREBASE_SENDER_ID` | Vacío; identificador del remitente FCM |
| `FIREBASE_PROJECT_ID` | Vacío; mismo proyecto configurado en el backend |

## Firebase Cloud Messaging

Sin configuración Firebase la app compila, pero no recibe push. El cliente habilita FCM en móvil
cuando las cuatro variables están completas y `USE_MOCKS=false`; el build web lo omite.
Registra la app Android con `com.example.fronted` y define los valores de tu proyecto:

```powershell
flutter run --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080 `
  --dart-define=FIREBASE_API_KEY=... --dart-define=FIREBASE_APP_ID=... `
  --dart-define=FIREBASE_SENDER_ID=... --dart-define=FIREBASE_PROJECT_ID=...
```

El backend debe usar el mismo proyecto; consulta [docs/fcm-real.md](../docs/fcm-real.md) y
[ADR 0005](../docs/adr/0005-push-fcm-y-radiotaxis.md). El `fcm-mock` local no entrega mensajes a
un teléfono real.

## Verificar y compilar

```powershell
flutter test --no-pub
flutter analyze
flutter build web --no-pub --dart-define=GATEWAY_URL=http://localhost:8080
flutter build apk --debug --no-pub --target-platform android-arm64 `
  --dart-define=GATEWAY_URL=http://10.0.2.2:8080
```

El APK queda en `build/app/outputs/flutter-apk/app-debug.apk`; el build web en `build/web/`.
Para teléfono físico vuelve a compilar el APK con la IP LAN, selecciona el dispositivo con
`flutter run -d ID` y permite la conexión de entrada al Gateway en el firewall de tu PC.
La configuración release exige HTTPS y aún usa la firma debug del proyecto; el comando anterior
genera un APK de prueba, no una publicación para tienda.

En el equipo de 8 GB de esta revisión, compilar Flutter mientras estaban activos todos los
contenedores y el navegador agotó la memoria disponible y produjo timeouts del Gateway.
En equipos similares, completa las compilaciones antes de ejecutar la demo. Puedes detener
temporalmente el entorno desde la raíz con `docker compose --profile demo stop` y volver a
levantarlo con el comando de preparación anterior; los volúmenes se conservan.

Las fixtures se regeneran desde la raíz con `node scripts/generate-mocks.mjs`.
El análisis histórico informa warnings y recomendaciones en la pantalla clásica; consulta los
resultados actuales en [QA del Sprint 3](../docs/qa-sprint-3.md). La prueba en Android físico y la
recepción de FCM real requieren un dispositivo, Firebase configurado y una red accesible.

Guion de prueba y video de menos de tres minutos: [demo del Sprint 3](../docs/demo-sprint-3.md).
La verificación por otro integrante debe registrarse con nombre y fecha en el documento de QA.

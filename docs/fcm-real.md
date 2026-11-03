# Prueba de Firebase Cloud Messaging real

Esta guía verifica la entrega de `ruta.actualizada` a un Android físico. No subas credenciales,
tokens FCM ni capturas que los revelen al repositorio.

## 1. Crear y preparar Firebase

1. Crear o seleccionar el proyecto Firebase de ColcaTrufis.
2. Registrar la aplicación Android con el identificador `com.example.fronted`.
3. En **Configuración del proyecto → Cuentas de servicio**, generar una clave JSON para una cuenta
   con permiso de Firebase Cloud Messaging.
4. Guardar el archivo como `secrets/firebase.json` en la raíz. La carpeta `secrets/` está ignorada
   por Git y Docker la monta de solo lectura en los servicios necesarios.
5. Copiar de la configuración de la app Android: API key, App ID, Sender ID y Project ID.

## 2. Configurar el backend local

Con Docker Desktop iniciado, ejecutar en PowerShell desde la raíz del repositorio:

```powershell
$env:FCM_URL = 'https://fcm.googleapis.com'
$env:FCM_PROJECT_ID = 'TU_PROJECT_ID'
$env:FCM_CREDENCIALES = '/secrets/firebase.json'
docker compose up -d --build --force-recreate api-notificaciones worker-notificaciones
```

No guardes esas variables ni el JSON en archivos versionados. El contenedor `fcm-mock` puede seguir
encendido: deja de recibir tráfico cuando `FCM_URL` apunta a Google.

## 3. Ejecutar Flutter en el teléfono

El teléfono y la PC deben compartir Wi-Fi. Averigua la IPv4 de la PC con `ipconfig` y sustituye
`IP-DE-TU-PC` y los valores del proyecto:

```powershell
cd fronted
flutter run --dart-define=GATEWAY_URL=http://IP-DE-TU-PC:8080 `
  --dart-define=FIREBASE_API_KEY=... --dart-define=FIREBASE_APP_ID=... `
  --dart-define=FIREBASE_SENDER_ID=... --dart-define=FIREBASE_PROJECT_ID=...
```

Acepta el permiso de notificaciones en Android. Inicia sesión con `demo@colcatrufis.local` /
`demo12345`, abre «Avisos por línea» y suscríbete a una línea. Al entrar al mapa, la app registra
el token FCM en `api-notificaciones`; el token nunca se devuelve mediante la API.

## 4. Comprobar el push y documentarlo

1. Desde el panel administrativo, edita y guarda la ruta de la línea suscrita.
2. Confirma en el teléfono que llega el aviso con el título y contenido del cambio.
3. Cierra sesión y repite la edición: ese dispositivo ya no debe recibir el aviso.
4. En [qa-sprint-2.md](qa-sprint-2.md), marca los pasos 6, 7 y 8, escribe el resultado y firma.

Si no llega el aviso, revisar primero:

- Permiso de notificaciones habilitado en Android.
- El Gateway es accesible por `http://IP-DE-TU-PC:8080` desde el teléfono.
- `FCM_PROJECT_ID` coincide con el JSON de la cuenta de servicio y con los `--dart-define`.
- `docker compose logs worker-notificaciones` no informa errores de OAuth ni de FCM.

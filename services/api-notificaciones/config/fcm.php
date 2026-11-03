<?php

return [
    // Sin FCM_URL ni FCM_CREDENCIALES el push queda apagado: solo se guardan las notificaciones.
    'habilitado' => (bool) (env('FCM_URL') || env('FCM_CREDENCIALES')),
    // En local apunta al contenedor fcm-mock; con credenciales reales, a Google.
    'url' => rtrim((string) env('FCM_URL', 'https://fcm.googleapis.com'), '/'),
    'proyecto' => env('FCM_PROJECT_ID', 'colcatrufis-local'),
    // Ruta al JSON de la cuenta de servicio de Firebase. Vacío = sin OAuth (fcm-mock).
    'credenciales' => env('FCM_CREDENCIALES'),
    'max_intentos' => (int) env('FCM_MAX_INTENTOS', 5),
];

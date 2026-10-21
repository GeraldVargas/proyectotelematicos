<?php

// Acuerdo del equipo (Sprint 1): JWT RS256 con los claims sub, rol y exp.
return [
    'keys_path' => env('JWT_KEYS_PATH', storage_path('keys')),
    'kid' => env('JWT_KID', 'colcatrufis-1'),
    'issuer' => env('JWT_ISSUER', 'colcatrufis-usuarios'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 3600),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 30),
];

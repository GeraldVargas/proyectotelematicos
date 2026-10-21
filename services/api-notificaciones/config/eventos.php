<?php

return [
    // Lista de Redis compartida por los servicios (ver docs/adr/0002). Productores: LPUSH; consumidor: BRPOP.
    'cola' => env('EVENTOS_COLA', 'colcatrufis:eventos'),
    'conexion_redis' => env('EVENTOS_REDIS_CONEXION', 'default'),
];

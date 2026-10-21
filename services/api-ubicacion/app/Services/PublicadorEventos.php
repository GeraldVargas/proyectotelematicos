<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class PublicadorEventos
{
    /**
     * Encola un evento con el sobre común acordado en docs/adr/0002 y devuelve el evento enviado.
     */
    public function publicar(string $tipo, array $datos): array
    {
        $evento = [
            'id' => (string) Str::uuid(),
            'tipo' => $tipo,
            'version' => 1,
            'origen' => 'api-ubicacion',
            'ocurrido_en' => now()->toIso8601String(),
            'datos' => $datos,
        ];

        Redis::connection(config('eventos.conexion_redis'))
            ->lpush(config('eventos.cola'), json_encode($evento, JSON_UNESCAPED_UNICODE));

        return $evento;
    }
}

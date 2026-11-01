<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class DespachadorEventos
{
    public function __construct(private readonly PublicadorEventos $publicador) {}

    public function despacharPendientes(): int
    {
        $ids = DB::table('eventos_salida')->whereNull('publicado_en')
            ->orderBy('created_at')->orderBy('id')->limit(50)->pluck('id');
        $enviados = 0;
        foreach ($ids as $id) {
            try {
                $enviados += DB::transaction(function () use ($id) {
                    $fila = DB::table('eventos_salida')->where('id', $id)->lockForUpdate()->first();
                    if (! $fila || $fila->publicado_en !== null) {
                        return 0;
                    }
                    // Ante un reinicio se reenvía el mismo UUID; Notificaciones deduplica por ese ID.
                    $this->publicador->enviar(json_decode($fila->payload, true, flags: JSON_THROW_ON_ERROR));
                    DB::table('eventos_salida')->where('id', $id)->update([
                        'publicado_en' => now(), 'updated_at' => now(), 'ultimo_error' => null,
                        'intentos' => $fila->intentos + 1,
                    ]);

                    return 1;
                });
            } catch (Throwable $e) {
                DB::table('eventos_salida')->where('id', $id)->update([
                    'intentos' => DB::raw('intentos + 1'), 'ultimo_error' => mb_substr($e->getMessage(), 0, 1000),
                    'updated_at' => now(),
                ]);
                report($e);
                // No descartar eventos ni saturar Redis cuando está caído.
                break;
            }
        }

        return $enviados;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Ruta;
use App\Services\PublicadorEventos;
use Illuminate\Console\Command;

/**
 * Prueba de concepto del Sprint 1: emite ruta.actualizada sin modificar la ruta.
 * En el Sprint 2 el mismo publicador lo llamará PUT /api/ubicacion/rutas/{id}.
 */
class PublicarEventoPrueba extends Command
{
    protected $signature = 'eventos:publicar-prueba {linea : Número de línea, p. ej. 252} {--descripcion=Desvío temporal por obras}';

    protected $description = 'Publica un evento ruta.actualizada de prueba para el servicio de Notificaciones';

    public function handle(PublicadorEventos $publicador): int
    {
        $ruta = Ruta::where('linea', $this->argument('linea'))->first();

        if (! $ruta) {
            $this->error('No existe la línea '.$this->argument('linea'));

            return self::FAILURE;
        }

        $evento = $publicador->publicar('ruta.actualizada', [
            'ruta_id' => $ruta->id,
            'linea' => $ruta->linea,
            'sindicato' => $ruta->sindicato,
            'cambio' => 'trazado',
            'descripcion' => $this->option('descripcion'),
        ]);

        $this->line(json_encode($evento, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

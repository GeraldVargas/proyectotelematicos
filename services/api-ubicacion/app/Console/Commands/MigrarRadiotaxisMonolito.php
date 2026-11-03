<?php

namespace App\Console\Commands;

use App\Models\Radiotaxi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra los sindicatos de radio taxi y su parada base. El monolito no guarda posiciones GPS:
 * el punto importado es la parada del sindicato, no un vehículo en movimiento.
 * Al migrar datos reales retira los de demostración.
 */
class MigrarRadiotaxisMonolito extends Command
{
    protected $signature = 'ubicacion:migrar-radiotaxis {--dry-run}';

    protected $description = 'Migra radio taxis y sus paradas del monolito ColcaTrufis (conexión "monolito") a PostGIS';

    public function handle(): int
    {
        $filas = DB::connection('monolito')->table('sindicato_radiotaxis')
            ->leftJoin('sindicato_radiotaxi_paradas', 'sindicato_radiotaxi_paradas.sindicato_radiotaxi_id', '=', 'sindicato_radiotaxis.id')
            ->get([
                'sindicato_radiotaxis.id', 'sindicato_radiotaxis.nombre_comercial', 'sindicato_radiotaxis.telefono_base',
                'sindicato_radiotaxi_paradas.latitud', 'sindicato_radiotaxi_paradas.longitud', 'sindicato_radiotaxi_paradas.estado',
            ]);

        $migrados = 0;

        foreach ($filas as $f) {
            if ($f->latitud === null || $f->longitud === null) {
                $this->warn("Radio taxi {$f->id} ({$f->nombre_comercial}): sin parada, no se muestra en el mapa");

                continue;
            }

            $this->line(sprintf('%s %s', $this->option('dry-run') ? '[dry-run]' : '→', $f->nombre_comercial));

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($f) {
                Radiotaxi::updateOrCreate(['monolito_id' => $f->id], [
                    'nombre' => $f->nombre_comercial,
                    'telefono' => $f->telefono_base,
                    'disponible' => (bool) $f->estado,
                    'origen' => 'monolito',
                ])->moverA((float) $f->longitud, (float) $f->latitud);
            });
            $migrados++;
        }

        if ($migrados > 0) {
            Radiotaxi::where('origen', 'demo')->delete();
        }

        $this->info("Radio taxis leídos: {$filas->count()}; migrados: {$migrados}");

        return self::SUCCESS;
    }
}

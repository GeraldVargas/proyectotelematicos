<?php

namespace App\Console\Commands;

use App\Services\CargadorRutas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Convierte los puntos ordenados de trufi_rutas (latitud/longitud) del monolito en
 * LineStrings PostGIS. Los puntos marcados es_parada pasan a la tabla paradas.
 */
class MigrarRutasMonolito extends Command
{
    protected $signature = 'ubicacion:migrar-monolito {--trufi=* : Solo estos idtrufi} {--dry-run}';

    protected $description = 'Migra rutas y paradas del monolito ColcaTrufis (conexión "monolito") a PostGIS';

    public function handle(CargadorRutas $cargador): int
    {
        $monolito = DB::connection('monolito');

        $trufis = $monolito->table('trufis')
            ->leftJoin('sindicatos', 'sindicatos.id', '=', 'trufis.sindicato_id')
            ->leftJoin('trufi_detalles', 'trufi_detalles.trufi_id', '=', 'trufis.idtrufi')
            ->when($this->option('trufi'), fn ($q, $ids) => $q->whereIn('trufis.idtrufi', $ids))
            ->get([
                'trufis.idtrufi', 'trufis.nom_linea', 'trufis.costo', 'trufis.frecuencia', 'trufis.tipo',
                'trufis.descripcion', 'trufis.estado', 'sindicatos.nombre as sindicato',
                'trufi_detalles.hora_entrada', 'trufi_detalles.hora_salida',
            ]);

        $migradas = 0;

        foreach ($trufis as $t) {
            $puntos = $monolito->table('trufi_rutas')
                ->where('idtrufi', $t->idtrufi)
                ->where('estado', 1)
                ->orderBy('orden')
                ->get(['latitud', 'longitud', 'es_parada']);

            if ($puntos->count() < 2) {
                $this->warn("Trufi {$t->idtrufi} ({$t->nom_linea}): sin trazado, hay que digitalizarlo");

                continue;
            }

            // GeoJSON: [lng, lat]
            $coordenadas = $puntos->map(fn ($p) => [(float) $p->longitud, (float) $p->latitud])->all();
            $paradas = $puntos->filter(fn ($p) => $p->es_parada)->values()
                ->map(fn ($p, $i) => [
                    'nombre' => "Parada {$t->nom_linea}-".($i + 1),
                    'coordinates' => [(float) $p->longitud, (float) $p->latitud],
                ])->all();

            $this->line(sprintf('%s %s: %d puntos, %d paradas', $this->option('dry-run') ? '[dry-run]' : '→', $t->nom_linea, count($coordenadas), count($paradas)));

            if ($this->option('dry-run')) {
                continue;
            }

            $cargador->guardar(['monolito_idtrufi' => $t->idtrufi], [
                'linea' => $t->nom_linea,
                'nombre' => trim("{$t->tipo} {$t->nom_linea}"),
                'sindicato' => $t->sindicato,
                'tipo' => $t->tipo,
                'tarifa' => $t->costo,
                'frecuencia_min' => $t->frecuencia,
                'horario' => $t->hora_entrada && $t->hora_salida ? substr($t->hora_entrada, 0, 5).'-'.substr($t->hora_salida, 0, 5) : null,
                'estado' => $t->estado ? 'activa' : 'suspendida',
            ], ['type' => 'LineString', 'coordinates' => $coordenadas], $paradas);
            $migradas++;
        }

        $this->info("Trufis leídos: {$trufis->count()}; rutas migradas: {$migradas}");

        return self::SUCCESS;
    }
}

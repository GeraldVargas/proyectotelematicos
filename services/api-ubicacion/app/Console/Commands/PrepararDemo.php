<?php

namespace App\Console\Commands;

use App\Models\Parada;
use App\Models\Radiotaxi;
use App\Models\Ruta;
use Illuminate\Console\Command;

class PrepararDemo extends Command
{
    protected $signature = 'demo:preparar';

    protected $description = 'Completa la semilla OSM y verifica rutas, paradas y geometrías para la demo local';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('La preparación de demo solo está disponible en local y testing.');

            return self::FAILURE;
        }

        if ($this->call('db:seed', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $datos = json_decode(file_get_contents(database_path('data/rutas_colcapirhua.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $paradasOsm = [];

        foreach ($datos['features'] as $feature) {
            $propiedades = $feature['properties'];
            $ruta = Ruta::where('osm_relation_id', $propiedades['osm_relation_id'])->first();
            $esperadas = array_unique(array_column($propiedades['paradas'], 'osm_id'));
            $presentes = $ruta?->paradas()->pluck('osm_node_id')->all() ?? [];

            if (! $ruta || array_diff($esperadas, $presentes)) {
                $this->error("La línea {$propiedades['linea']} está incompleta. Revise sus paradas antes de presentar.");

                return self::FAILURE;
            }

            $paradasOsm = array_merge($paradasOsm, $esperadas);
        }

        $relaciones = array_column(array_column($datos['features'], 'properties'), 'osm_relation_id');
        $rutas = Ruta::whereIn('osm_relation_id', $relaciones);
        $paradas = Parada::whereIn('osm_node_id', array_unique($paradasOsm));

        foreach ([$rutas, $paradas] as $consulta) {
            if ((clone $consulta)->whereRaw('(geom IS NULL OR ST_IsEmpty(geom) OR NOT ST_IsValid(geom) OR ST_SRID(geom) <> 4326)')->exists()) {
                $this->error('Hay geometrías ausentes o inválidas en el conjunto OSM.');

                return self::FAILURE;
            }
        }

        $taxistas = Radiotaxi::where('origen', 'demo')->count();
        $this->info("Demo lista: {$rutas->count()} rutas OSM, {$paradas->count()} paradas OSM y {$taxistas} radio taxis demo.");
        if ($taxistas === 0) {
            $this->warn('Se conservan los radio taxis importados; no se agregan posiciones ficticias sobre datos existentes.');
        }

        return self::SUCCESS;
    }
}

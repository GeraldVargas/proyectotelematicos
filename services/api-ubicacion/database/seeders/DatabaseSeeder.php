<?php

namespace Database\Seeders;

use App\Services\CargadorRutas;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Carga las líneas reales que pasan por Colcapirhua desde database/data/rutas_colcapirhua.geojson.
     * Fuente: OpenStreetMap (ODbL), relaciones mapeadas por Trufi Association.
     * Es idempotente: se puede ejecutar en cada arranque del contenedor.
     */
    public function run(CargadorRutas $cargador): void
    {
        $datos = json_decode(file_get_contents(database_path('data/rutas_colcapirhua.geojson')), true);

        foreach ($datos['features'] as $feature) {
            $p = $feature['properties'];

            $cargador->guardar(
                ['osm_relation_id' => $p['osm_relation_id']],
                [
                    'linea' => $p['linea'],
                    'nombre' => $p['nombre'],
                    'sindicato' => $p['sindicato'],
                    'tipo' => $p['tipo'],
                    'desde' => $p['desde'],
                    'hasta' => $p['hasta'],
                    'color' => $p['color'],
                ],
                $feature['geometry'],
                array_map(fn ($s) => [
                    'nombre' => $s['nombre'],
                    'coordinates' => $s['coordinates'],
                    'osm_node_id' => $s['osm_id'],
                ], $p['paradas']),
            );
        }
    }
}

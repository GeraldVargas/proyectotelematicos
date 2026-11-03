<?php

namespace Database\Seeders;

use App\Models\Radiotaxi;
use App\Models\Ruta;
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

            // Sembrar solo datos nuevos: reiniciar Docker no debe borrar ediciones del administrador.
            if (Ruta::where('osm_relation_id', $p['osm_relation_id'])->exists()) {
                continue;
            }

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

        $this->radiotaxisDeDemostracion();
    }

    /**
     * No hay datos reales de radio taxis sin la BD del monolito. Estos son ficticios, van marcados
     * con origen=demo y solo se crean si la tabla está vacía (ubicacion:migrar-radiotaxis los retira).
     */
    private function radiotaxisDeDemostracion(): void
    {
        if (Radiotaxi::exists()) {
            return;
        }

        foreach ([[-66.2405, -17.3895], [-66.2510, -17.3930], [-66.2300, -17.3850], [-66.2450, -17.3990]] as $i => [$lng, $lat]) {
            Radiotaxi::create([
                'nombre' => 'Radio Taxi Demo '.($i + 1),
                'sindicato' => 'Datos de demostración',
                'origen' => 'demo',
            ])->moverA($lng, $lat);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Parada;
use App\Models\Ruta;
use Illuminate\Support\Facades\DB;

/**
 * Inserta o actualiza rutas y paradas a partir de geometrías GeoJSON.
 * Lo usan el seeder (datos de OpenStreetMap) y la migración desde el monolito.
 */
class CargadorRutas
{
    /**
     * @param  array  $clave  columna única que identifica el origen, p. ej. ['osm_relation_id' => 123]
     * @param  array  $lineString  geometría GeoJSON LineString
     * @param  array  $paradas  lista de ['nombre', 'coordinates' => [lng, lat], 'osm_node_id'?]
     */
    public function guardar(array $clave, array $atributos, array $lineString, array $paradas = []): Ruta
    {
        return DB::transaction(function () use ($clave, $atributos, $lineString, $paradas) {
            $ruta = Ruta::updateOrCreate($clave, $atributos);
            $this->asignarGeometria('rutas', $ruta->id, $lineString);

            $pivot = [];
            foreach ($paradas as $p) {
                $parada = isset($p['osm_node_id'])
                    ? Parada::firstOrCreate(['osm_node_id' => $p['osm_node_id']], ['nombre' => $p['nombre']])
                    : Parada::create(['nombre' => $p['nombre']]);
                $this->asignarGeometria('paradas', $parada->id, ['type' => 'Point', 'coordinates' => $p['coordinates']]);
                // En recorridos circulares una parada puede repetirse; se conserva la primera aparición
                $pivot[$parada->id] ??= ['orden' => count($pivot) + 1];
            }
            $ruta->paradas()->sync($pivot);

            return $ruta;
        });
    }

    private function asignarGeometria(string $tabla, int $id, array $geometria): void
    {
        // ST_GeomFromGeoJSON respeta el orden [lng, lat] del estándar
        DB::update(
            "UPDATE {$tabla} SET geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?",
            [json_encode($geometria), $id]
        );
    }
}

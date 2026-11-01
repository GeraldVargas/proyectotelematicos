<?php

namespace App\Http\Controllers;

use App\Models\Parada;
use App\Support\GeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParadaController extends Controller
{
    // GET /api/ubicacion/paradas
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'ruta_id' => ['sometimes', 'integer'],
        ]);

        $paradas = Parada::conGeoJson()
            ->with('rutas:id,linea,color')
            ->when($filtros['ruta_id'] ?? null, fn ($q, $v) => $q->whereHas('rutas', fn ($r) => $r->where('rutas.id', $v)))
            ->orderBy('id')
            ->get();

        return $this->respuesta($paradas);
    }

    public function cercanas(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radio' => ['sometimes', 'numeric', 'between:1,5000'],
            'limite' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $punto = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $paradas = Parada::conGeoJson()->with('rutas:id,linea,color')
            ->selectRaw("ST_Distance(paradas.geom::geography, {$punto}) AS distancia_m", [$datos['lng'], $datos['lat']])
            ->whereRaw("ST_DWithin(paradas.geom::geography, {$punto}, ?)", [$datos['lng'], $datos['lat'], $datos['radio'] ?? 300])
            ->orderBy('distancia_m')->orderBy('paradas.id')->limit($datos['limite'] ?? 50)->get();

        return $this->respuesta($paradas, true);
    }

    private function respuesta($paradas, bool $conDistancia = false): JsonResponse
    {
        $features = $paradas->map(fn (Parada $p) => GeoJson::feature($p->id, $p->geometry_json, [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'rutas' => $p->rutas->map(fn ($r) => ['id' => $r->id, 'linea' => $r->linea, 'color' => $r->color])->values(),
        ] + ($conDistancia ? ['distancia_m' => round((float) $p->distancia_m, 2)] : [])));

        return response()->json(GeoJson::collection($features), 200, ['Content-Type' => 'application/geo+json'], JSON_UNESCAPED_UNICODE);
    }
}

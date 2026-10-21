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

        $features = $paradas->map(fn (Parada $p) => GeoJson::feature($p->id, $p->geometry_json, [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'rutas' => $p->rutas->map(fn ($r) => ['id' => $r->id, 'linea' => $r->linea, 'color' => $r->color])->values(),
        ]));

        return response()->json(GeoJson::collection($features), 200, ['Content-Type' => 'application/geo+json'], JSON_UNESCAPED_UNICODE);
    }
}

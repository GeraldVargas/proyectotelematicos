<?php

namespace App\Http\Controllers;

use App\Models\Ruta;
use App\Support\GeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RutaController extends Controller
{
    // GET /api/ubicacion/rutas
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'linea' => ['sometimes', 'string', 'max:20'],
            'sindicato' => ['sometimes', 'string', 'max:255'],
            'estado' => ['sometimes', 'in:activa,suspendida'],
        ]);

        $rutas = Ruta::conGeoJson()
            ->when($filtros['linea'] ?? null, fn ($q, $v) => $q->where('linea', $v))
            ->when($filtros['sindicato'] ?? null, fn ($q, $v) => $q->where('sindicato', 'ilike', "%{$v}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderBy('linea')
            ->get();

        return $this->geojson(GeoJson::collection(
            $rutas->map(fn (Ruta $r) => GeoJson::feature($r->id, $r->geometry_json, $this->propiedades($r)))
        ));
    }

    // GET /api/ubicacion/rutas/{id}
    public function show(int $id): JsonResponse
    {
        $ruta = Ruta::conGeoJson()->with('paradas:id,nombre')->find($id);

        if (! $ruta) {
            return response()->json(['message' => 'Ruta no encontrada'], 404);
        }

        $propiedades = $this->propiedades($ruta) + [
            'paradas' => $ruta->paradas->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'orden' => $p->pivot->orden,
            ])->values(),
        ];

        return $this->geojson(GeoJson::feature($ruta->id, $ruta->geometry_json, $propiedades));
    }

    private function propiedades(Ruta $ruta): array
    {
        return collect(Ruta::ATRIBUTOS)->mapWithKeys(fn ($c) => [$c => $ruta->{$c}])->all();
    }

    private function geojson(array $cuerpo): JsonResponse
    {
        return response()->json($cuerpo, 200, ['Content-Type' => 'application/geo+json'], JSON_UNESCAPED_UNICODE);
    }
}

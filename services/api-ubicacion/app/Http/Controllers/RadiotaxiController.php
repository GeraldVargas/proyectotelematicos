<?php

namespace App\Http\Controllers;

use App\Models\Radiotaxi;
use App\Support\GeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadiotaxiController extends Controller
{
    // GET /api/ubicacion/radiotaxis: posición actual de los disponibles
    public function index(): JsonResponse
    {
        $radiotaxis = Radiotaxi::conGeoJson()
            ->where('disponible', true)
            ->whereNotNull('geom')
            ->orderBy('id')
            ->get();

        return $this->geojson(GeoJson::collection($radiotaxis->map(fn (Radiotaxi $r) => $this->feature($r))));
    }

    // PUT /api/ubicacion/radiotaxis/{id}/posicion (solo admin)
    public function actualizarPosicion(Request $request, int $id): JsonResponse
    {
        $radiotaxi = Radiotaxi::find($id);

        if (! $radiotaxi) {
            return response()->json(['message' => 'Radio taxi no encontrado'], 404);
        }

        $datos = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'disponible' => ['sometimes', 'boolean'],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'sindicato' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($radiotaxi, $datos) {
            $radiotaxi->update(\Illuminate\Support\Arr::except($datos, ['lat', 'lng']));
            $radiotaxi->moverA((float) $datos['lng'], (float) $datos['lat']);
        });

        return $this->geojson($this->feature(Radiotaxi::conGeoJson()->findOrFail($id)));
    }

    private function feature(Radiotaxi $r): array
    {
        return GeoJson::feature($r->id, $r->geometry_json, [
            'id' => $r->id,
            'nombre' => $r->nombre,
            'sindicato' => $r->sindicato,
            'telefono' => $r->telefono,
            'disponible' => $r->disponible,
            'origen' => $r->origen,
            'posicion_actualizada_en' => $r->posicion_actualizada_en?->toIso8601String(),
        ]);
    }

    private function geojson(array $cuerpo): JsonResponse
    {
        return response()->json($cuerpo, 200, ['Content-Type' => 'application/geo+json'], JSON_UNESCAPED_UNICODE);
    }
}

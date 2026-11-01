<?php

namespace App\Http\Controllers;

use App\Models\Ruta;
use App\Services\PublicadorEventos;
use App\Support\GeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    // PUT /api/ubicacion/rutas/{id}: la ruta y su evento se guardan juntos en PostGIS.
    public function update(Request $request, int $id, PublicadorEventos $publicador): JsonResponse
    {
        if (! Ruta::whereKey($id)->exists()) {
            return response()->json(['message' => 'Ruta no encontrada'], 404);
        }
        $datos = $request->validate([
            'geometry' => ['required', 'array:type,coordinates'],
            'geometry.type' => ['required', 'in:LineString'],
            'geometry.coordinates' => ['required', 'array', 'list', 'min:2', 'max:10000', function ($attribute, $value, $fail) {
                if (! is_array($value)) {
                    return;
                }
                if (count(array_unique(array_map('json_encode', $value))) < 2) {
                    $fail('El trazado debe tener al menos dos posiciones diferentes.');
                }
            }],
            'geometry.coordinates.*' => ['required', 'array', 'size:2'],
            'geometry.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'geometry.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'sindicato' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'in:activa,suspendida'],
            'color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'horario' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tarifa' => ['sometimes', 'nullable', 'numeric', 'between:0,9999.99'],
            'frecuencia_min' => ['sometimes', 'nullable', 'integer', 'between:1,1440'],
            'descripcion' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        // Normalizar cadenas numéricas antes de crear GeoJSON: sus posiciones son números.
        $geometry = $datos['geometry'];
        $geometry['coordinates'] = array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $geometry['coordinates']);
        $eventoId = DB::transaction(function () use ($id, $datos, $geometry, $publicador) {
            $ruta = Ruta::lockForUpdate()->findOrFail($id);
            $ruta->fill(Arr::except($datos, ['geometry', 'descripcion']))->save();
            DB::update('UPDATE rutas SET geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?', [json_encode($geometry), $id]);
            $evento = $publicador->preparar('ruta.actualizada', [
                'ruta_id' => $ruta->id, 'linea' => $ruta->linea, 'sindicato' => $ruta->sindicato,
                'cambio' => 'trazado', 'descripcion' => $datos['descripcion'],
            ]);
            DB::table('eventos_salida')->insert([
                'id' => $evento['id'], 'payload' => json_encode($evento, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $evento['id'];
        });
        $cuerpo = $this->show($id)->getData(true);
        $cuerpo['meta'] = ['evento_id' => $eventoId, 'notificacion' => 'pendiente'];

        return $this->geojson($cuerpo);
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

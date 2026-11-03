<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class NotificacionController extends Controller
{
    // GET /api/notificaciones
    public function index(Request $request): JsonResponse
    {
        $notificaciones = Notificacion::where('user_id', $request->attributes->get('user_id'))
            ->latest('id')
            ->paginate(max(1, min((int) $request->query('por_pagina', 20), 100)));

        return response()->json($notificaciones);
    }

    // GET /api/notificaciones/suscripciones
    public function suscripciones(Request $request): JsonResponse
    {
        return response()->json(
            Suscripcion::where('user_id', $request->attributes->get('user_id'))->orderBy('linea')->get(['id', 'linea', 'created_at'])
        );
    }

    // POST /api/notificaciones/suscripciones
    public function suscribir(Request $request): JsonResponse
    {
        $datos = $request->validate(['linea' => ['required', 'string', 'max:20']]);

        $suscripcion = Suscripcion::firstOrCreate([
            'user_id' => $request->attributes->get('user_id'),
            'linea' => $datos['linea'],
        ]);

        return response()->json($suscripcion->only(['id', 'linea', 'created_at']), $suscripcion->wasRecentlyCreated ? 201 : 200);
    }

    // DELETE /api/notificaciones/suscripciones/{lineaId}
    public function desuscribir(Request $request, string $lineaId): Response|JsonResponse
    {
        $borradas = Suscripcion::where('user_id', $request->attributes->get('user_id'))->where('linea', $lineaId)->delete();

        return $borradas ? response()->noContent() : response()->json(['message' => 'No está suscrito a esa línea'], 404);
    }

    // GET /api/notificaciones/historial?desde=&hasta=&page=&por_pagina=
    public function historial(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date', 'after_or_equal:desde'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $notificaciones = Notificacion::where('user_id', $request->attributes->get('user_id'))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $this->inicioDelFiltro($v)))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $this->finDelFiltro($v)))
            ->latest('id')
            ->paginate($filtros['por_pagina'] ?? 20);

        return response()->json($notificaciones);
    }

    private function inicioDelFiltro(string $fecha): Carbon
    {
        return Carbon::parse($fecha)->utc();
    }

    // Una fecha sin hora incluye el día completo
    private function finDelFiltro(string $fecha): Carbon
    {
        $fin = Carbon::parse($fecha)->utc();

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fin->endOfDay() : $fin;
    }
}

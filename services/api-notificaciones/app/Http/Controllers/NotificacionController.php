<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    // GET /api/notificaciones
    public function index(Request $request): JsonResponse
    {
        $notificaciones = Notificacion::where('user_id', $request->attributes->get('user_id'))
            ->latest('id')
            ->paginate(min((int) $request->query('por_pagina', 20), 100));

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
}

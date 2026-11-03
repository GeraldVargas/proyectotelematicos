<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DispositivoController extends Controller
{
    // POST /api/notificaciones/dispositivos
    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'plataforma' => ['sometimes', 'in:android,ios,web'],
        ]);
        $userId = $request->attributes->get('user_id');

        $dispositivo = DB::transaction(function () use ($datos, $userId) {
            // Si otra cuenta inició sesión en este teléfono, sus push pendientes no deben llegarle
            Dispositivo::where('token', $datos['token'])->where('user_id', '!=', $userId)->delete();

            return Dispositivo::updateOrCreate(
                ['token' => $datos['token']],
                ['user_id' => $userId, 'plataforma' => $datos['plataforma'] ?? 'android'],
            );
        });

        return response()->json($dispositivo->only(['id', 'plataforma', 'created_at']), $dispositivo->wasRecentlyCreated ? 201 : 200);
    }

    // DELETE /api/notificaciones/dispositivos (al cerrar sesión). El token va en el cuerpo para no dejarlo en logs de URL.
    public function eliminar(Request $request): Response|JsonResponse
    {
        $datos = $request->validate(['token' => ['required', 'string', 'max:512']]);

        $borrados = Dispositivo::where('token', $datos['token'])
            ->where('user_id', $request->attributes->get('user_id'))
            ->delete();

        return $borrados ? response()->noContent() : response()->json(['message' => 'Dispositivo no registrado'], 404);
    }
}

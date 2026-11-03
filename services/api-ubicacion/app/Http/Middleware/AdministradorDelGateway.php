<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdministradorDelGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        // Estos headers los sobrescribe el Gateway a partir del JWT verificado.
        // La API permanece sin puerto público en Docker.
        $id = $request->header('X-User-Id');
        if (! $id || ! ctype_digit($id)) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        if ($request->header('X-User-Rol') !== 'admin') {
            return response()->json(['message' => 'Esta acción requiere rol de administrador'], 403);
        }

        return $next($request);
    }
}

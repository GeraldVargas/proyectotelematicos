<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El API Gateway valida el JWT y copia el claim sub en X-User-Id (propagate_claims).
 * Este servicio no se expone fuera de la red de Docker, así que confía en ese header.
 */
class UsuarioDelGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-User-Id');

        if (! $id || ! ctype_digit($id)) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $request->attributes->set('user_id', (int) $id);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * El Gateway ya valida el JWT; este servicio lo vuelve a verificar porque es el emisor
 * y tiene la clave pública a mano (defensa en profundidad si alguien llega sin pasar por el Gateway).
 */
class AutenticarJwt
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token no proporcionado'], 401);
        }

        try {
            $claims = $this->jwt->decodificar($token);
        } catch (Throwable) {
            return response()->json(['message' => 'Token inválido o expirado'], 401);
        }

        $user = User::find($claims->sub ?? null);

        if (! $user || ! $user->activo) {
            return response()->json(['message' => 'Usuario no autorizado'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

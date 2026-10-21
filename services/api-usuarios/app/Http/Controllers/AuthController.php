<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    // POST /api/usuarios/registro
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // El registro público siempre crea usuarios de la app; los admins se crean desde el panel
        $user = User::create($datos + ['rol' => 'usuario']);

        return response()->json($this->jwt->emitirPar($user), 201);
    }

    // POST /api/usuarios/login
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $datos['email'])->first();

        if (! $user || ! Hash::check($datos['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (! $user->activo) {
            return response()->json(['message' => 'Usuario desactivado'], 403);
        }

        return response()->json($this->jwt->emitirPar($user));
    }

    // GET /api/usuarios/perfil
    public function perfil(Request $request): JsonResponse
    {
        return response()->json($request->user()->toPerfil());
    }

    // POST /api/usuarios/refresh
    public function refresh(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $actual = $this->jwt->buscarRefresh($datos['refresh_token']);

        if (! $actual || ! $actual->vigente() || ! $actual->user->activo) {
            return response()->json(['message' => 'Refresh token inválido o expirado'], 401);
        }

        // Rotación: cada refresh token sirve una sola vez
        $par = DB::transaction(function () use ($actual) {
            $actual->update(['revoked_at' => now()]);

            return $this->jwt->emitirPar($actual->user);
        });

        return response()->json($par);
    }
}

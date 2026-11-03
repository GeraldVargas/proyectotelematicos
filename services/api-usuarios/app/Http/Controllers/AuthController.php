<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    // GET /api/usuarios/perfil/{id}: cada usuario ve el suyo; admin y encargado, cualquiera
    public function perfilPorId(Request $request, int $id): JsonResponse
    {
        $actual = $request->user();

        if ($actual->id !== $id && ! in_array($actual->rol, ['admin', 'encargado'], true)) {
            return response()->json(['message' => 'No tiene permiso para ver este perfil'], 403);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user->toPerfil());
    }

    // GET /api/usuarios/lista (solo admin)
    public function lista(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'admin') {
            return response()->json(['message' => 'Solo un administrador puede listar usuarios'], 403);
        }

        $datos = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $usuarios = User::orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20)
            ->through(fn (User $u) => $u->toPerfil());

        return response()->json($usuarios);
    }

    // DELETE /api/usuarios/sesion: con refresh_token cierra ese dispositivo; sin él, todas las sesiones
    public function logout(Request $request): Response
    {
        $datos = $request->validate([
            'refresh_token' => ['sometimes', 'string'],
        ]);

        $request->user()->refreshTokens()
            ->whereNull('revoked_at')
            ->when($datos['refresh_token'] ?? null, fn ($q, $token) => $q->where('token_hash', hash('sha256', $token)))
            ->update(['revoked_at' => now()]);

        return response()->noContent();
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

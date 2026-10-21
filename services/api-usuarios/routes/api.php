<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AutenticarJwt;
use Illuminate\Support\Facades\Route;

// Públicas (el Gateway no exige token)
Route::post('/registro', [AuthController::class, 'registro']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Protegidas
Route::middleware(AutenticarJwt::class)->group(function () {
    Route::get('/perfil', [AuthController::class, 'perfil']);
});

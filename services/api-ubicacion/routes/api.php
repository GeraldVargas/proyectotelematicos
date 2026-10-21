<?php

use App\Http\Controllers\ParadaController;
use App\Http\Controllers\RutaController;
use Illuminate\Support\Facades\Route;

// Todas las rutas son protegidas: el Gateway valida el JWT antes de reenviar.
// Fuera del Sprint 1: paradas/cercanas, radiotaxis y PUT rutas/{id}.
Route::get('/rutas', [RutaController::class, 'index']);
Route::get('/rutas/{id}', [RutaController::class, 'show'])->whereNumber('id');
Route::get('/paradas', [ParadaController::class, 'index']);

<?php

use App\Http\Controllers\ParadaController;
use App\Http\Controllers\RadiotaxiController;
use App\Http\Controllers\RutaController;
use App\Http\Middleware\AdministradorDelGateway;
use Illuminate\Support\Facades\Route;

// Todas las rutas son protegidas: el Gateway valida el JWT antes de reenviar.
// Sprint 2: consultas de cercanía y edición administrativa con eventos persistidos.
Route::get('/rutas', [RutaController::class, 'index']);
Route::get('/rutas/{id}', [RutaController::class, 'show'])->whereNumber('id');
Route::get('/paradas', [ParadaController::class, 'index']);
Route::get('/paradas/cercanas', [ParadaController::class, 'cercanas']);
Route::put('/rutas/{id}', [RutaController::class, 'update'])->whereNumber('id')->middleware(AdministradorDelGateway::class);
Route::get('/radiotaxis', [RadiotaxiController::class, 'index']);
Route::put('/radiotaxis/{id}/posicion', [RadiotaxiController::class, 'actualizarPosicion'])->whereNumber('id')->middleware(AdministradorDelGateway::class);

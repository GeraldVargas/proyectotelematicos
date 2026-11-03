<?php

use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Middleware\UsuarioDelGateway;
use Illuminate\Support\Facades\Route;

Route::middleware(UsuarioDelGateway::class)->group(function () {
    Route::get('/', [NotificacionController::class, 'index']);
    Route::get('/suscripciones', [NotificacionController::class, 'suscripciones']);
    Route::post('/suscripciones', [NotificacionController::class, 'suscribir']);
    Route::delete('/suscripciones/{lineaId}', [NotificacionController::class, 'desuscribir']);
    Route::get('/historial', [NotificacionController::class, 'historial']);
    Route::post('/dispositivos', [DispositivoController::class, 'registrar']);
    Route::delete('/dispositivos', [DispositivoController::class, 'eliminar']);
});

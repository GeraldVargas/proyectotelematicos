<?php

use App\Http\Controllers\NotificacionController;
use App\Http\Middleware\UsuarioDelGateway;
use Illuminate\Support\Facades\Route;

Route::middleware(UsuarioDelGateway::class)->group(function () {
    Route::get('/', [NotificacionController::class, 'index']);
    Route::get('/suscripciones', [NotificacionController::class, 'suscripciones']);
    Route::post('/suscripciones', [NotificacionController::class, 'suscribir']);
});

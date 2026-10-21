<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // Convención acordada: /api/{servicio}/... (el Gateway reenvía la ruta sin reescribirla)
        apiPrefix: 'api/notificaciones',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Las respuestas de error siempre en JSON, sin depender del header Accept del cliente
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();

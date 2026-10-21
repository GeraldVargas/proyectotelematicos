<?php

namespace Tests;

use App\Services\JwtService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Claves desechables, generadas una sola vez por ejecución de la suite
        $ruta = sys_get_temp_dir().'/colcatrufis-jwt-tests';
        config(['jwt.keys_path' => $ruta]);
        $this->app->forgetInstance(JwtService::class);
        app(JwtService::class)->generarClaves();
    }
}

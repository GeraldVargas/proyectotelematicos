<?php

namespace Tests;

use App\Services\JwtService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Aplicar antes de RefreshDatabase: los env de Docker pueden prevalecer sobre PHPUnit.
        $app['env'] = 'testing';
        $app['config']->set([
            'app.env' => 'testing', 'logging.default' => 'null',
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        $app['db']->purge('sqlite');

        return $app;
    }

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

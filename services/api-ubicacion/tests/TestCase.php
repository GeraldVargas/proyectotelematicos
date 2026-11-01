<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['env'] = 'testing';
        $app['config']->set([
            'app.env' => 'testing', 'logging.default' => 'null',
            'database.default' => 'pgsql', 'database.connections.pgsql.database' => 'ubicacion_test',
            'database.connections.pgsql.url' => null,
        ]);
        $app['db']->purge('pgsql');

        return $app;
    }
}

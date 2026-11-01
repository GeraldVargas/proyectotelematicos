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
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        $app['db']->purge('sqlite');

        return $app;
    }
}

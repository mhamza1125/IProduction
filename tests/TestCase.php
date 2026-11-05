<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run tests against anything but a database whose name ends in
     * "_test", and never in production, so RefreshDatabase cannot wipe live data.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $database = (string) $config->get('database.connections.'.$config->get('database.default').'.database');

        if ($app->environment('production') || ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests: database [{$database}] is not a *_test database (environment: {$app->environment()}).");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests do not need compiled CSS/JS.
        $this->withoutVite();
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Abort before RefreshDatabase can touch a real database. When a cached
     * config is active (php artisan optimize / config:cache run locally),
     * phpunit.xml's <env> overrides are silently ignored and the suite boots
     * against the LOCAL MySQL database — where RefreshDatabase runs
     * migrate:fresh and wipes all dev data. This guard runs before
     * setUpTraits(), i.e. before any migration can fire.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $bootedSafely = $this->app->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';

        if (! $bootedSafely) {
            fwrite(STDERR, sprintf(
                "\nTests aborted: booted as env '%s' on connection '%s' (database '%s') instead of testing/sqlite/:memory:." .
                " phpunit.xml's env overrides are not being applied — most likely a cached config (bootstrap/cache/config.php)." .
                " Run `php artisan config:clear` and retry.\n",
                $this->app->environment(),
                config('database.default'),
                config(sprintf('database.connections.%s.database', config('database.default'))),
            ));

            exit(1);
        }
    }
}

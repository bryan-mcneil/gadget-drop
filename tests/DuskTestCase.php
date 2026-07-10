<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Abort before DatabaseMigrations can touch a real database — the same
     * paranoia as tests/TestCase.php::refreshApplication(). Dusk swaps .env
     * with .env.dusk.local, but a cached config (bootstrap/cache/config.php)
     * makes Laravel ignore it and boot against the LOCAL MySQL database,
     * where migrate:fresh would wipe all dev data. This guard runs before
     * setUpTraits(), i.e. before any migration can fire.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $database = (string) config('database.connections.sqlite.database');

        $bootedSafely = $this->app->environment('testing')
            && config('database.default') === 'sqlite'
            && str_ends_with($database, 'dusk.sqlite');

        if (! $bootedSafely) {
            fwrite(STDERR, sprintf(
                "\nDusk aborted: booted as env '%s' on connection '%s' (database '%s') instead of testing/sqlite/dusk.sqlite.".
                ' Check .env.dusk.local and run `php artisan config:clear` (a cached config ignores the Dusk env swap).'."\n",
                $this->app->environment(),
                config('database.default'),
                config(sprintf('database.connections.%s.database', config('database.default'))),
            ));

            exit(1);
        }

        // The sqlite connector requires the file to exist before it will
        // connect; DatabaseMigrations handles the schema from there.
        $path = str_starts_with($database, 'database/')
            ? base_path($database)
            : $database;

        if (! file_exists($path)) {
            touch($path);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}

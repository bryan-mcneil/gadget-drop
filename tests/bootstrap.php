<?php

/*
 * Test-run safety guard (2026-07-06 incident).
 *
 * When bootstrap/cache/config.php exists (php artisan optimize / config:cache
 * run locally), Laravel loads it verbatim and never re-evaluates env() — so
 * every <env> override in phpunit.xml is silently ignored, the suite boots as
 * env "local" against the real MySQL database, and RefreshDatabase runs
 * migrate:fresh on it. That wiped the local gadget_drop database once.
 *
 * A cached config is a disposable build artifact, so the safe move is to
 * delete it here — before PHPUnit loads a single test class — and let the
 * suite boot from .env + phpunit.xml as intended.
 */
$cachedConfig = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    if (@unlink($cachedConfig)) {
        fwrite(STDERR, "[tests/bootstrap.php] Deleted stale bootstrap/cache/config.php — a cached config makes Laravel ignore phpunit.xml's env overrides and run tests against the real database.".PHP_EOL);
    } else {
        fwrite(STDERR, '[tests/bootstrap.php] ABORTING: bootstrap/cache/config.php exists and could not be deleted. Run `php artisan config:clear` and retry — tests must never boot from a cached config.'.PHP_EOL);
        exit(1);
    }
}

require __DIR__.'/../vendor/autoload.php';

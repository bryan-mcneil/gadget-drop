<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Production cron (hPanel) fires `php artisan schedule:run` HOURLY at minute 0,
 | not every minute, to save shared-hosting CPU. The scheduler only runs tasks
 | whose time matches the minute it executes, so every task below MUST be
 | scheduled at minute :00. If a task ever needs finer cadence, increase the
 | cron frequency in hPanel first. Times are UTC (the app timezone).
 */
Schedule::command('newsletter:send')->fridays()->at('14:00');

// Self-healing image pipeline: backfill any missing WebP variants (idempotent).
Schedule::command('images:optimize')->dailyAt('04:00');

// The database cache store never sweeps expired rows it does not re-read.
Schedule::command('cache:prune-expired')->dailyAt('05:00');

// Lock the day's Drop Price puzzle. Hourly cron means it runs within the first
// UTC hour; the read layer falls back to the latest puzzle until then.
Schedule::command('dropprice:lock')->dailyAt('00:00');
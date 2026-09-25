<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Assign computed attributes hourly
// Schedule::command('assign:computed-attributes')
//     ->hourly()
//     ->withoutOverlapping()
//     ->runInBackground();

// Background jobs — enable on ONE domain only (e.g. production). Set SCHEDULER_ENABLED=false
// on dev/staging clones that share the same database to avoid duplicate workers.
// Includes: alerts:poll, low-fuel alerts, address backfill, fuel refill, ignition stabilize,
// io9 stabilize, event columns, backups, geofence queue.
if (filter_var(env('SCHEDULER_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
    Schedule::command('alerts:poll')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('events:check-columns')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('backup:cleanup-old')->daily()->at('01:00');
    Schedule::command('backup:database-only')->daily()->at('01:30');

    Schedule::command('traccar:backfill-addresses --continuous')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('traccar:backfill-event-positions --continuous')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    // BITSole legacy io9 stabilizer — keep until ignition-attribute path is validated in prod
    Schedule::command('traccar:stabilize-io9')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('traccar:stabilize-ignition-attributes')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('positions:observe-fuel-refill')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('positions:observe-geofence-alerts --interval=5')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('queue:work database --queue=geofence --sleep=1 --tries=3 --timeout=120')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('alerts:check-low-fuel')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Assign computed attributes hourly
// Schedule::command('assign:computed-attributes')
//     ->hourly()
//     ->withoutOverlapping()
//     ->runInBackground();

// Poll alerts service - runs continuously
// In production, ensure the scheduler is running (cron)
Schedule::command('alerts:poll')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Check for missing columns in tc_events table every 5 minutes
Schedule::command('events:check-columns')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Database-only backups daily (can be disabled via env)
if (env('BACKUP_ENABLED', true)) {
    Schedule::command('backup:cleanup-old')->daily()->at('01:00');
    Schedule::command('backup:database-only')->daily()->at('01:30');
}

// Backfill missing addresses in tc_positions
// Runs continuously (restarts if stopped) to fix blank addresses
Schedule::command('traccar:backfill-addresses --continuous')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

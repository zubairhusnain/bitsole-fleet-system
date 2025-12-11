<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('traccar:assign-computed-attributes', function () {
    $svc = app(\App\Services\PermissionService::class);
    $req = new \Illuminate\Http\Request();
    $summary = $svc->assignComputedAttributesToAllDevices($req);
    $this->info('Assigned: ' . ($summary['assigned'] ?? 0));
    $this->info('Failed: ' . ($summary['failed'] ?? 0));
    $this->info('Devices: ' . ($summary['deviceCount'] ?? 0));
    $this->info('Attributes: ' . ($summary['attributeCount'] ?? 0));
    $errs = $summary['errors'] ?? [];
    if (is_array($errs) && count($errs)) {
        $this->warn('Errors:');
        foreach (array_slice($errs, 0, 10) as $e) { $this->line('- ' . $e); }
    }
})->purpose('Assign all computed attributes to all Traccar devices');

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

// Backup database and files daily
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
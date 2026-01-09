<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "--- Devices ---\n";
    $deviceCount = \App\Models\Devices::count();
    echo "Total Devices: " . $deviceCount . "\n";
    if ($deviceCount > 0) {
        $firstDevice = \App\Models\Devices::with('tcDevice')->first();
        echo "First Device ID: " . $firstDevice->id . "\n";
        echo "First Device TC Name: " . ($firstDevice->tcDevice->name ?? 'N/A') . "\n";
    }

    echo "\n--- Incidents ---\n";
    $incidentCount = \App\Models\Incident::count();
    echo "Total Incidents: " . $incidentCount . "\n";
    if ($incidentCount > 0) {
        $firstIncident = \App\Models\Incident::first();
        echo "First Incident ID: " . $firstIncident->id . "\n";
        echo "First Incident Vehicle Label: " . $firstIncident->vehicle_label . "\n";
        echo "First Incident Device ID: " . $firstIncident->device_id . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

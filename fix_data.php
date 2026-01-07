<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Clearing existing incidents...\n";
    \App\Models\Incident::truncate();

    echo "Fetching real devices...\n";
    // Fetch devices with their TC device details to get real names
    $devices = \App\Models\Devices::with('tcDevice')->get();

    if ($devices->isEmpty()) {
        die("No devices found to link incidents to.\n");
    }

    echo "Found " . $devices->count() . " devices. Generating 100 incidents...\n";

    $types = ['Collision', 'Overspeed', 'Harsh Braking', 'Geofence Exit', 'Geofence Enter', 'Idling'];
    $drivers = ['Adam', 'Bella', 'Chong', 'Danish', 'Eric', 'Farah'];

    $count = 0;
    for ($i = 0; $i < 100; $i++) {
        // Pick a random real device
        $device = $devices->random();
        $tc = $device->tcDevice;

        // Construct real label
        $name = $tc->name ?? 'Unknown Device';
        $uniqueId = $tc->uniqueid ?? '';
        // Format similar to deviceOptions: "UniqueId - Name" or just "Name"
        $label = $uniqueId ? "$uniqueId - $name" : $name;

        // Ensure at least 10 records are for TODAY to be visible immediately
        if ($i < 10) {
             $start = now()->subHours(rand(1, 12));
        } else {
             $start = now()->subDays(rand(0, 60))->subHours(rand(0, 24));
        }

        $end = (clone $start)->addMinutes(rand(10, 180));

        \App\Models\Incident::create([
            'device_id' => $device->device_id, // Ensure we use the correct linking ID
            'vehicle_label' => $label,
            'type_model' => $types[array_rand($types)],
            'incident_start' => $start,
            'incident_end' => $end,
            'impact_time' => (clone $start)->addSeconds(rand(60, 600)),
            'driver' => $drivers[array_rand($drivers)],
            'description' => "Detected " . strtolower($types[array_rand($types)]) . " event at " . $start->format('H:i'),
            'remarks' => rand(0, 1) ? 'Investigated' : 'Pending Review',
        ]);
        $count++;
    }

    echo "Successfully inserted $count incidents linked to real vehicles.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

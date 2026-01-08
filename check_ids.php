<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $device = \App\Models\Devices::with('tcDevice')->first();
    if ($device) {
        echo "Wrapper ID (devices.id): " . $device->id . "\n";
        echo "Wrapper Device ID (devices.device_id): " . $device->device_id . "\n";
        if ($device->tcDevice) {
            echo "TC Device ID (tc_devices.id): " . $device->tcDevice->id . "\n";
            echo "Match? " . ($device->device_id == $device->tcDevice->id ? "YES" : "NO") . "\n";
        } else {
            echo "No linked TC Device.\n";
        }
    } else {
        echo "No devices found.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

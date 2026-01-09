<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Devices;

$devices = Devices::whereHas('tcDevice', function($q) {
    $q->where('name', 'like', '%JRA1002%');
})->with('tcDevice')->get();

foreach ($devices as $d) {
    echo "ID: " . $d->device_id . "\n";
    echo "Name: " . $d->tcDevice->name . "\n";
    echo "UniqueId: " . $d->tcDevice->uniqueid . "\n";
}

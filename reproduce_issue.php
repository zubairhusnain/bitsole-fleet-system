<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\MaintenanceController;
use App\Services\MaintenanceService;
use App\Services\DeviceService;
use Illuminate\Support\Facades\Auth;

use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

// 1. Find a user
$user = User::first();
if (!$user) {
    die("No user found.\n");
}
Auth::login($user);
echo "Logged in as user ID: " . $user->id . "\n";

// 1.5 Login to Traccar to get session
class TraccarLogin {
    use Curl;
    public function login() {
        $data = 'email=' . Config::get('constants.Constants.adminEmail') . '&password=' . Config::get('constants.Constants.adminPassword');
        $response = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);

        if ($response->responseCode == 200) {
             // Extract cookie from response headers handled by Curl trait which sets session('cookie')
             // But Curl trait sets session(['cookie' => ...]) which works if Session driver is working.
             // In CLI, we might need to manually capture it.
             // The Curl trait does: if (preg_match(...)) session(['cookie'=>$c[1]]);
             // Let's rely on that or check if we need to manually set it on user.
             return session('cookie');
        }
        return null;
    }
}

$traccar = new TraccarLogin();
$cookie = $traccar->login();

if (!$cookie) {
    // Curl trait might have set it in session array, but maybe not persisted?
    // Let's try to fetch it from session store if it was set.
    $cookie = session('cookie');
}

if (!$cookie) {
    die("Failed to login to Traccar. Check constants.\n");
}
echo "Traccar Cookie: " . substr($cookie, 0, 20) . "...\n";

// Update user with this session so the Service can use it
$user->traccarSession = $cookie;
$user->save();

// 2. Instantiate Controller
$maintenanceService = app(MaintenanceService::class);
$deviceService = app(DeviceService::class);
$controller = new MaintenanceController($maintenanceService, $deviceService);

// 3. Create a test maintenance record
// We need a valid device ID first to assign later.
$devices = $maintenanceService->getAllDevices(new Request());
if (empty($devices)) {
    die("No devices found in Traccar.\n");
}
// Pick a device ID
$firstDeviceId = is_array($devices[0]) ? $devices[0]['id'] : $devices[0]->id;
echo "Found device ID: $firstDeviceId\n";

$request = new Request();
$request->setUserResolver(function () use ($user) { return $user; });
$request->merge([
    'name' => 'Test Maintenance ' . time(),
    'type' => 'oilChange',
    'start' => 0,
    'period' => 1000,
    'deviceId' => 'all' // Start with ALL
]);

echo "Creating maintenance record assigned to ALL...\n";
$response = $controller->store($request);
$data = $response->getData(true);

if (!isset($data['id'])) {
    print_r($data);
    die("Failed to create maintenance record.\n");
}
$maintenanceId = $data['id'];
echo "Created Maintenance ID: $maintenanceId\n";

// Check assignments
$assigned = $maintenanceService->getDevicesForMaintenance($request, $maintenanceId);
echo "Initial assignment count: " . count($assigned) . "\n";

// 4. Update to Single Device
echo "Updating to Single Device ID: $firstDeviceId...\n";
$updateRequest = new Request();
$updateRequest->setUserResolver(function () use ($user) { return $user; });
$updateRequest->merge([
    'name' => 'Test Maintenance Updated',
    'type' => 'oilChange',
    'start' => 0,
    'period' => 1000,
    'deviceId' => $firstDeviceId // Switch to ONE
]);

$updateResponse = $controller->update($updateRequest, $maintenanceId);
$updateData = $updateResponse->getData(true);

// 5. Check assignments again
$finalAssigned = $maintenanceService->getDevicesForMaintenance($request, $maintenanceId);
echo "Final assignment count: " . count($finalAssigned) . "\n";
$finalIds = [];
foreach ($finalAssigned as $d) {
    $id = is_array($d) ? $d['id'] : $d->id;
    $finalIds[] = $id;
    echo " - Device: " . $id . "\n";
}

// 6. Check if it failed
if (count($finalAssigned) !== 1) {
    echo "FAIL: Expected 1 device, found " . count($finalAssigned) . "\n";
} else {
    if ($finalIds[0] == $firstDeviceId) {
        echo "SUCCESS: Assigned to single device correctly.\n";
    } else {
        echo "FAIL: Assigned device ID mismatch.\n";
    }
}

// Cleanup
echo "Deleting maintenance record...\n";
$maintenanceService->delete($request, $maintenanceId);

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Config;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;

// 1. Get User
$user = User::where('email', 'distributor@gmail.com')->first();
if (!$user) $user = User::first();
echo "Using User: " . $user->email . "\n";

// 2. Setup
$host = Config::get('constants.Constants.host');
$cookie = $user->traccarSession;
$maintenanceId = 7;
$deviceIdToRemove = 22;

$service = app(MaintenanceService::class);
$request = Request::create('/', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });

// Helper to check assignment
function checkAssignment($service, $request, $mId, $dId) {
    $devices = $service->getDevicesForMaintenance($request, $mId);
    $ids = $devices ? array_column($devices, 'id') : [];
    return in_array($dId, $ids);
}

if (!checkAssignment($service, $request, $maintenanceId, $deviceIdToRemove)) {
    echo "Device $deviceIdToRemove is NOT assigned initially. Cannot test removal.\n";
    exit;
}
echo "Device $deviceIdToRemove IS assigned.\n";

// 3. Raw Curl Test
function rawDelete($url, $cookie, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Cookie: ' . $cookie
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $result];
}

echo "--- Attempting Raw DELETE with Accept Header ---\n";
// Ensure Integers
$payload = json_encode(['deviceId' => (int)$deviceIdToRemove, 'maintenanceId' => (int)$maintenanceId]);
echo "Payload: $payload\n";

$res = rawDelete($host . '/api/permissions', $cookie, $payload);
echo "Result Code: " . $res['code'] . "\n";
echo "Result Body: " . $res['body'] . "\n";

// 4. Verify
if (checkAssignment($service, $request, $maintenanceId, $deviceIdToRemove)) {
    echo "FAILURE: Device $deviceIdToRemove is STILL assigned!\n";
} else {
    echo "SUCCESS: Device $deviceIdToRemove was removed.\n";
}

// 5. Check Groups
echo "--- Checking Group Assignments ---\n";
function getGroups($url, $cookie, $mId) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "/api/groups?maintenanceId=" . $mId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: ' . $cookie]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$groups = getGroups($host, $cookie, $maintenanceId);
echo "Groups: " . $groups . "\n";

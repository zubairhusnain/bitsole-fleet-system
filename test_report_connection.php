<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\Pool;

// 1. Login to get session
$email = Config::get('constants.Constants.adminEmail');
$password = Config::get('constants.Constants.adminPassword');
$host = rtrim(Config::get('constants.Constants.host'), '/');

echo "Logging in to $host as $email...\n";

$response = Http::asForm()->post("$host/api/session", [
    'email' => $email,
    'password' => $password
]);

if (!$response->successful()) {
    echo "Login failed: " . $response->status() . "\n";
    echo $response->body() . "\n";
    exit(1);
}

$cookies = $response->cookies();
$cookieJar = $response->cookies();
// Extract JSESSIONID
$sessionId = '';
foreach ($cookies as $cookie) {
    if ($cookie->getName() === 'JSESSIONID') {
        $sessionId = 'JSESSIONID=' . $cookie->getValue();
        break;
    }
}

if (!$sessionId) {
    echo "No JSESSIONID found.\n";
    // dump cookies
    print_r($cookies);
    // Try to get from header if not in cookie jar (Guzzle handles it)
    $headerCookie = $response->header('Set-Cookie');
    if ($headerCookie) {
         echo "Set-Cookie header: $headerCookie\n";
         // Parse it simply
         if (preg_match('/JSESSIONID=[^;]+/', $headerCookie, $matches)) {
             $sessionId = $matches[0];
         }
    }
}

echo "Session ID: $sessionId\n";

// 2. Prepare request params
// Use a date range and device ID that likely has data.
// Need a valid device ID. I'll fetch devices first.
$devicesResponse = Http::withHeaders(['Cookie' => $sessionId])->get("$host/api/devices");
$devices = $devicesResponse->json();
if (empty($devices)) {
    echo "No devices found.\n";
    exit(1);
}
$deviceId = $devices[0]['id'];
echo "Using Device ID: $deviceId\n";

$from = date('Y-m-d\TH:i:00\Z', strtotime('-1 day'));
$to = date('Y-m-d\TH:i:00\Z');
$fullQuery = "deviceId=$deviceId&from=$from&to=$to";

echo "Query: $fullQuery\n";

// 3. Test Http::get (Single)
echo "\n--- Testing Single Http::get ---\n";
$url = "$host/api/reports/summary?$fullQuery";
echo "URL: $url\n";
$singleResp = Http::withHeaders([
    'Cookie' => $sessionId,
    'Accept' => 'application/json'
])->get($url);

echo "Status: " . $singleResp->status() . "\n";
// echo "Body: " . substr($singleResp->body(), 0, 200) . "...\n";

// 4. Test Http::pool
echo "\n--- Testing Http::pool ---\n";
$eventTypes = 'harshBraking,harshAcceleration,overspeed,fuelIncrease';

$responses = Http::pool(fn (Pool $pool) => [
    $pool->as('summary')->withHeaders([
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->get("$host/api/reports/summary?$fullQuery"),
    $pool->as('stops')->withHeaders([
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->get("$host/api/reports/stops?$fullQuery"),
]);

$summaryResp = $responses['summary'];
echo "Pool Summary Status: " . $summaryResp->status() . "\n";
// echo "Pool Summary Body: " . substr($summaryResp->body(), 0, 200) . "...\n";

if ($singleResp->successful() && !$summaryResp->successful()) {
    echo "FAILURE: Single request worked but Pool failed.\n";
} elseif (!$singleResp->successful()) {
    echo "FAILURE: Single request failed.\n";
} else {
    echo "SUCCESS: Both worked.\n";
}

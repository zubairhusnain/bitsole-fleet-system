<?php
// Fetch all devices and their latest positions from Tracking Platform using the existing cookie jar.
// Usage:
//   php scripts/traccar_fetch_all.php --host=http://52.77.249.227:8082 --cookie=cookies_traccar.txt [--email=admin@example.com --password=secret]
// If email/password are provided, the script will perform a login and update the cookie jar before fetching.

declare(strict_types=1);

// --- CLI options parsing ---
$opts = [
    'host' => 'http://52.77.249.227:8082',
    'cookie' => 'cookies_traccar.txt',
    'email' => null,
    'password' => null,
];
foreach ($argv as $arg) {
    if (preg_match('/^--host=(.+)$/', $arg, $m)) { $opts['host'] = $m[1]; }
    elseif (preg_match('/^--cookie=(.+)$/', $arg, $m)) { $opts['cookie'] = $m[1]; }
    elseif (preg_match('/^--email=(.+)$/', $arg, $m)) { $opts['email'] = $m[1]; }
    elseif (preg_match('/^--password=(.+)$/', $arg, $m)) { $opts['password'] = $m[1]; }
}

$host = rtrim($opts['host'], '/');
$cookieFile = $opts['cookie'];

// Ensure cookie file exists
if (!file_exists($cookieFile)) {
    // Create an empty cookie file to avoid cURL warnings; login will populate if credentials are provided
    touch($cookieFile);
}

function curlRequest(string $method, string $url, array $headers = [], ?array $form = null, ?string $rawBody = null): array {
    global $cookieFile;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $httpHeaders = array_merge(['Accept: application/json'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($form !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
            // Content-Type is form by default when using POSTFIELDS string
            $httpHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($rawBody !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$status, $body, $err];
}

function jsonGet(string $url): array {
    [$status, $body, $err] = curlRequest('GET', $url);
    if ($err) throw new RuntimeException("cURL error: $err");
    if ($status < 200 || $status >= 300) throw new RuntimeException("HTTP $status for $url; body: $body");
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException("Invalid JSON from $url");
    return $data;
}

function loginIfRequested(string $host, ?string $email, ?string $password): void {
    if (!$email || !$password) return; // skip login if not provided
    [$status, $body, $err] = curlRequest('POST', $host.'/api/session', [], [
        'email' => $email,
        'password' => $password,
    ]);
    if ($err) throw new RuntimeException("Login cURL error: $err");
    if ($status < 200 || $status >= 300) throw new RuntimeException("Login failed (HTTP $status): $body");
}

// Optional login step if credentials provided
if ($opts['email'] && $opts['password']) {
    fwrite(STDERR, "Logging in to $host as {$opts['email']}...\n");
    loginIfRequested($host, $opts['email'], $opts['password']);
}

// Fetch devices
fwrite(STDERR, "Fetching devices...\n");
$devices = jsonGet($host.'/api/devices');
file_put_contents('devices_all.json', json_encode($devices, JSON_PRETTY_PRINT));
$ids = [];
foreach ($devices as $d) { if (isset($d['id'])) $ids[] = (int)$d['id']; }
file_put_contents('device_ids.txt', implode("\n", $ids)."\n");
fwrite(STDERR, sprintf("Found %d devices.\n", count($ids)));

// Fetch positions for each device
$index = [];
foreach ($ids as $id) {
    fwrite(STDERR, "Fetching positions for device $id...\n");
    try {
        $positions = jsonGet($host.'/api/positions?deviceId='.$id);
        file_put_contents("positions_{$id}.json", json_encode($positions, JSON_PRETTY_PRINT));
        $count = is_array($positions) ? count($positions) : 0;
        $lastTime = null;
        if ($count > 0) {
            $last = $positions[$count - 1];
            $lastTime = $last['serverTime'] ?? ($last['fixTime'] ?? ($last['deviceTime'] ?? null));
        }
        $index[] = ['id' => $id, 'count' => $count, 'lastTime' => $lastTime];
    } catch (Throwable $e) {
        fwrite(STDERR, "Error fetching positions for $id: ".$e->getMessage()."\n");
        $index[] = ['id' => $id, 'count' => 0, 'lastTime' => null, 'error' => $e->getMessage()];
    }
    // Be polite to the server
    usleep(200_000); // 200ms between requests
}
file_put_contents('positions_index.json', json_encode($index, JSON_PRETTY_PRINT));

fwrite(STDERR, "Done. Files written: devices_all.json, device_ids.txt, positions_*.json, positions_index.json\n");
?>
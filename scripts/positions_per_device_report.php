<?php
// Produce a per-device CSV report with predicted odometer and fuel-level keys/values from latest positions.
// Usage:
//   php scripts/positions_per_device_report.php

declare(strict_types=1);

function latestPosition(array $positions): ?array {
    if (!is_array($positions) || !count($positions)) return null;
    return $positions[count($positions) - 1];
}

function predictOdometer(array $attrs): array {
    // Named keys first
    foreach (['odometer','odometerKm','odometer_km'] as $k) {
        if (array_key_exists($k, $attrs) && is_numeric($attrs[$k])) {
            return [$k, $attrs[$k] + 0];
        }
    }
    // Teltonika-coded fallback: 16 then 389
    foreach ([['16','io16'], ['389','io389']] as $pair) {
        foreach ($pair as $k) {
            if (array_key_exists($k, $attrs) && is_numeric($attrs[$k]) && ($attrs[$k] + 0) > 0) {
                return [$k, $attrs[$k] + 0];
            }
        }
    }
    // Distance-based fallback
    foreach (['totalDistance','distance','tripDistance'] as $k) {
        if (array_key_exists($k, $attrs) && is_numeric($attrs[$k])) {
            return [$k, $attrs[$k] + 0];
        }
    }
    return [null, null];
}

function predictFuel(array $attrs): array {
    // Named keys first
    foreach (['fuelLevel','fuelPercent','fuelLiters','fuel'] as $k) {
        if (array_key_exists($k, $attrs) && is_numeric($attrs[$k])) {
            return [$k, $attrs[$k] + 0];
        }
    }
    // Common CAN/BLE fuel percent key
    foreach (['io113','113'] as $k) {
        if (array_key_exists($k, $attrs) && is_numeric($attrs[$k])) {
            $val = $attrs[$k] + 0;
            if ($val >= 0 && $val <= 100) return [$k, $val];
        }
    }
    // Heuristic: first io* key with 0..100
    foreach ($attrs as $k => $v) {
        if (preg_match('/^io\d+$/', (string)$k) && is_numeric($v)) {
            $val = $v + 0;
            if ($val >= 0 && $val <= 100) return [$k, $val];
        }
    }
    return [null, null];
}

$devices = [];
if (file_exists(__DIR__.'/../devices_all.json')) {
    $devicesArr = json_decode(file_get_contents(__DIR__.'/../devices_all.json'), true);
    if (is_array($devicesArr)) {
        foreach ($devicesArr as $d) {
            $devices[(int)($d['id'] ?? 0)] = ($d['name'] ?? '') ?: '';
        }
    }
}

$files = glob(__DIR__.'/../positions_*.json');
sort($files);

$out = fopen(__DIR__.'/../positions_report.csv', 'w');
fputcsv($out, [
    'deviceId', 'deviceName', 'positionTime',
    'predOdometerKey', 'predOdometerValue',
    'predFuelKey', 'predFuelValue',
    'has_totalDistance', 'has_distance', 'has_16', 'has_io16', 'has_389', 'has_io389'
]);

foreach ($files as $fn) {
    $m = [];
    if (!preg_match('/positions_(\d+)\.json$/', $fn, $m)) continue;
    $id = (int)$m[1];
    $arr = json_decode(file_get_contents($fn), true);
    if (!is_array($arr) || !count($arr)) {
        fputcsv($out, [$id, $devices[$id] ?? '', '', null, null, null, null, 0, 0, 0, 0, 0, 0]);
        continue;
    }
    $pos = latestPosition($arr);
    $attrs = is_array($pos['attributes'] ?? null) ? $pos['attributes'] : [];
    $time = $pos['serverTime'] ?? ($pos['fixTime'] ?? ($pos['deviceTime'] ?? ''));

    [$odoKey, $odoVal] = predictOdometer($attrs);
    [$fuelKey, $fuelVal] = predictFuel($attrs);

    $has = [
        'totalDistance' => array_key_exists('totalDistance', $attrs) ? 1 : 0,
        'distance' => array_key_exists('distance', $attrs) ? 1 : 0,
        '16' => array_key_exists('16', $attrs) ? 1 : 0,
        'io16' => array_key_exists('io16', $attrs) ? 1 : 0,
        '389' => array_key_exists('389', $attrs) ? 1 : 0,
        'io389' => array_key_exists('io389', $attrs) ? 1 : 0,
    ];

    fputcsv($out, [
        $id,
        $devices[$id] ?? '',
        $time,
        $odoKey,
        $odoVal,
        $fuelKey,
        $fuelVal,
        $has['totalDistance'],
        $has['distance'],
        $has['16'],
        $has['io16'],
        $has['389'],
        $has['io389'],
    ]);
}

fclose($out);
fwrite(STDERR, "Report written: positions_report.csv\n");
?>
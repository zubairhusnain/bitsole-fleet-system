<?php
// Analyze downloaded positions_*.json files to predict odometer and fuel-level keys
// Usage:
//   php scripts/positions_analyze.php

declare(strict_types=1);

function latestPosition(array $positions): ?array {
    if (!is_array($positions) || !count($positions)) return null;
    return $positions[count($positions) - 1];
}

$files = glob(__DIR__.'/../positions_*.json');
sort($files);

$summary = [
    'device_count' => 0,
    'odometer_named' => 0,
    'odometer_totalDistance' => 0,
    'odometer_distance' => 0,
    'has_16' => 0,
    'has_389' => 0,
    'has_io16' => 0,
    'has_io389' => 0,
    'fuel_key_counts' => [],
    'odometer_key_counts' => [],
];

foreach ($files as $fn) {
    $arr = json_decode(file_get_contents($fn), true);
    if (!is_array($arr) || !count($arr)) continue;
    $pos = latestPosition($arr);
    $attrs = is_array($pos['attributes'] ?? null) ? $pos['attributes'] : [];
    $summary['device_count']++;

    // Named odometer keys
    foreach (['odometer','odometerKm','odometer_km'] as $k) {
        if (array_key_exists($k, $attrs)) {
            $summary['odometer_named']++;
            $summary['odometer_key_counts'][$k] = ($summary['odometer_key_counts'][$k] ?? 0) + 1;
            break;
        }
    }
    // Distance-based keys
    foreach (['totalDistance','distance','tripDistance'] as $k) {
        if (array_key_exists($k, $attrs)) {
            $summary['odometer_key_counts'][$k] = ($summary['odometer_key_counts'][$k] ?? 0) + 1;
        }
    }
    if (array_key_exists('totalDistance', $attrs)) $summary['odometer_totalDistance']++;
    if (array_key_exists('distance', $attrs)) $summary['odometer_distance']++;

    // Numeric-coded odometer keys
    foreach (['16','389'] as $k) {
        if (array_key_exists($k, $attrs)) $summary['has_'.$k]++;
        $io = 'io'.$k;
        if (array_key_exists($io, $attrs)) $summary['has_'.$io]++;
    }

    // Fuel candidates: io* keys with values in 0..100
    foreach ($attrs as $k => $v) {
        if (preg_match('/^io\d+$/', (string)$k) && is_numeric($v)) {
            $num = $v + 0;
            if ($num >= 0 && $num <= 100) {
                $summary['fuel_key_counts'][$k] = ($summary['fuel_key_counts'][$k] ?? 0) + 1;
            }
        }
    }
}

file_put_contents(__DIR__.'/../positions_analysis.json', json_encode($summary, JSON_PRETTY_PRINT));

// Write convenience tops
$fuelCounts = $summary['fuel_key_counts'] ?? [];
arsort($fuelCounts);
$lines = [];
$n = 0;
foreach ($fuelCounts as $k => $v) { $lines[] = "$k: $v"; if (++$n >= 15) break; }
file_put_contents(__DIR__.'/../fuel_candidates_top.txt', implode("\n", $lines)."\n");

$odoCounts = $summary['odometer_key_counts'] ?? [];
arsort($odoCounts);
$lines = [];
foreach ($odoCounts as $k => $v) { $lines[] = "$k: $v"; }
file_put_contents(__DIR__.'/../odometer_keys_top.txt', implode("\n", $lines)."\n");

fwrite(STDERR, "Analysis complete. Files written: positions_analysis.json, fuel_candidates_top.txt, odometer_keys_top.txt\n");
?>
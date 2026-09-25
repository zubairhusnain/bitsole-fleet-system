<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TcEvent;

echo "=== tc_notifications (geofence) ===\n";
$notifs = DB::connection('pgsql')->select("
    SELECT id, type, attributes FROM tc_notifications
    WHERE type ILIKE '%geofence%' OR (type = 'alarm' AND CAST(attributes AS json)::text ILIKE '%geofence%')
    ORDER BY id
");
foreach ($notifs as $n) {
    echo "  #{$n->id} type={$n->type} attrs={$n->attributes}\n";
}
if (! $notifs) {
    echo "  (none)\n";
}

echo "\n=== geofence events (last 90 days) ===\n";
$events = DB::connection('pgsql')->select("
    SELECT type, COUNT(*) AS c FROM tc_events
    WHERE eventtime > NOW() - INTERVAL '90 days'
      AND type IN ('geofenceEnter', 'geofenceExit')
    GROUP BY type
");
foreach ($events as $e) {
    echo "  {$e->type}: {$e->c}\n";
}
if (! $events) {
    echo "  (none)\n";
}

echo "\n=== recent geofence events ===\n";
$samples = DB::connection('pgsql')->select("
    SELECT id, deviceid, type, eventtime, geofenceid
    FROM tc_events
    WHERE type IN ('geofenceEnter', 'geofenceExit')
    ORDER BY eventtime DESC LIMIT 10
");
foreach ($samples as $s) {
    echo "  #{$s->id} dev={$s->deviceid} {$s->type} geo={$s->geofenceid} {$s->eventtime}\n";
}
if (! $samples) {
    echo "  (none)\n";
}

echo "\n=== tc_device_notification for geofenceEnter/Exit ===\n";
$links = DB::connection('pgsql')->select("
    SELECT dn.deviceid, n.id, n.type, d.name
    FROM tc_device_notification dn
    JOIN tc_notifications n ON n.id = dn.notificationid
    JOIN tc_devices d ON d.id = dn.deviceid
    WHERE n.type IN ('geofenceEnter', 'geofenceExit')
    ORDER BY dn.deviceid, n.type
");
foreach ($links as $l) {
    echo "  dev #{$l->deviceid} {$l->name} -> notif #{$l->id} {$l->type}\n";
}
if (! $links) {
    echo "  (NONE - this is likely the problem)\n";
}

echo "\n=== alarm geofence notification links ===\n";
$alarms = DB::connection('pgsql')->select("
    SELECT dn.deviceid, n.id, n.attributes, d.name
    FROM tc_device_notification dn
    JOIN tc_notifications n ON n.id = dn.notificationid
    JOIN tc_devices d ON d.id = dn.deviceid
    WHERE n.type = 'alarm'
      AND CAST(n.attributes AS json)::text ILIKE '%geofence%'
");
foreach ($alarms as $a) {
    echo "  dev #{$a->deviceid} {$a->name} notif #{$a->id} {$a->attributes}\n";
}
if (! $alarms) {
    echo "  (none)\n";
}

echo "\n=== per-device: raw vs visible (geofence events, 90d) ===\n";
$devices = DB::connection('pgsql')->select("
    SELECT DISTINCT deviceid FROM tc_events
    WHERE type IN ('geofenceEnter', 'geofenceExit')
      AND eventtime > NOW() - INTERVAL '90 days'
    ORDER BY deviceid
");
foreach ($devices as $d) {
    $did = (int) $d->deviceid;
    $raw = (int) DB::connection('pgsql')->selectOne("
        SELECT COUNT(*) AS c FROM tc_events
        WHERE deviceid = ? AND type IN ('geofenceEnter','geofenceExit')
          AND eventtime > NOW() - INTERVAL '90 days'
    ", [$did])->c;
    $visible = TcEvent::where('deviceid', $did)
        ->whereIn('type', ['geofenceEnter', 'geofenceExit'])
        ->where('eventtime', '>', now()->subDays(90))
        ->withEnabledNotifications()
        ->count();
    $name = DB::connection('pgsql')->selectOne('SELECT name FROM tc_devices WHERE id = ?', [$did])->name ?? '?';
    echo "  #{$did} {$name}: raw={$raw} visible={$visible}".($raw > 0 && $visible === 0 ? ' *** HIDDEN ***' : '')."\n";
}

echo "\n=== ever geofenceEnter/Exit (all time) ===\n";
$ever = DB::connection('pgsql')->selectOne("
    SELECT COUNT(*) AS c, MIN(eventtime) AS mn, MAX(eventtime) AS mx
    FROM tc_events WHERE type IN ('geofenceEnter', 'geofenceExit')
");
echo "  count={$ever->c} first={$ever->mn} last={$ever->mx}\n";

echo "\n=== alarm events with geofence in attributes ===\n";
$alarmGeo = DB::connection('pgsql')->select("
    SELECT CAST(attributes AS json)->>'alarm' AS alarm, COUNT(*) AS c
    FROM tc_events
    WHERE type = 'alarm'
      AND CAST(attributes AS json)->>'alarm' ILIKE '%geofence%'
    GROUP BY alarm ORDER BY c DESC LIMIT 10
");
foreach ($alarmGeo as $a) {
    echo "  {$a->alarm}: {$a->c}\n";
}
if (! $alarmGeo) {
    echo "  (none)\n";
}

echo "\n=== positions with geofenceids (last 7 days, sample) ===\n";
$posGeo = DB::connection('pgsql')->select("
    SELECT deviceid, fixtime, geofenceids
    FROM tc_positions
    WHERE fixtime > NOW() - INTERVAL '7 days'
      AND geofenceids IS NOT NULL AND geofenceids <> ''
    ORDER BY fixtime DESC LIMIT 8
");
foreach ($posGeo as $p) {
    echo "  dev={$p->deviceid} {$p->fixtime} geofenceids={$p->geofenceids}\n";
}
if (! $posGeo) {
    echo "  (none - Traccar may not be computing geofence membership on positions)\n";
}

$assign = DB::connection('pgsql')->select('
    SELECT dg.deviceid, dg.geofenceid, d.name, g.name AS geofence_name
    FROM tc_device_geofence dg
    JOIN tc_devices d ON d.id = dg.deviceid
    JOIN tc_geofences g ON g.id = dg.geofenceid
    ORDER BY dg.deviceid LIMIT 15
');
foreach ($assign as $a) {
    echo "  dev #{$a->deviceid} {$a->name} -> geo #{$a->geofenceid} {$a->geofence_name}\n";
}

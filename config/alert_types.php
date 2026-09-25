<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Known alert types (Traccar + app)
    |--------------------------------------------------------------------------
    |
    | Shown immediately in the Alerts filter. Add new built-in types here when
    | you introduce them in code. For one-off labels, use "labels" below.
    |
    */
    'known' => [
        'alarm',
        'commandResult',
        'deviceInactive',
        'deviceMoving',
        'deviceOffline',
        'deviceOnline',
        'deviceOverspeed',
        'deviceStopped',
        'deviceUnknown',
        'driverChanged',
        'frequentIgnition',
        'geofenceEnter',
        'geofenceExit',
        'ignitionOff',
        'ignitionOn',
        'lowBattery',
        'lowFuelCritical',
        'lowFuelWarning',
        'maintenance',
        'motion',
        'powerCut',
        'queuedCommandSent',
        'sos',
        'textMessage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional display labels
    |--------------------------------------------------------------------------
    |
    | Override how a type appears in the UI without changing frontend code.
    | Keys are event "type" values from tc_events.
    |
    */
    'labels' => [
        // 'myCustomType' => 'My Custom Alert',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom type discovery
    |--------------------------------------------------------------------------
    |
    | Types seen in tc_events for the user's devices are merged with "known".
    | Cached to avoid scanning the events table on every page load.
    |
    */
    'discover_custom' => true,
    'cache_ttl' => 600,
    'discover_limit' => 100,
];

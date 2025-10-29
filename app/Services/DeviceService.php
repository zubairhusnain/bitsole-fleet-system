<?php

namespace App\Services;

use App\Models\DeviceGroup;
use App\Models\Devices;
use Illuminate\Http\Request;
use App\Helpers\Curl;
use App\Helpers\Helpers;
use DateTimeZone;
use DateTime;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Services\GeofencesService;
class DeviceService
{
    use Curl;


    // public function getAllDevices($request)
    // {
    //     $id = session('tc_user_id');
    //     $sessionId = $request->user()->traccarSession ?? session('cookie');

    //     $offset = $request->input('offset', 0);
    //     $limit = $request->input('limit', 10);
    //     $offset2 = $offset + 1;

    //     $date = now();
    //     $from = $date->subMonths(6)->format('Y-m-d\TH:i:s\Z');
    //     $to = now()->format('Y-m-d\TH:i:s\Z');

    //     $user = $request->user();
    //     if (!$user) {
    //         return ['devices' => [], 'nextDevice' => []];
    //     }

    //     // **Step 1: Fetch Relevant Devices Efficiently**
    //     $query = Devices::where('device_type', 0);

    //     if ($user->user_role == 0) {
    //         $query->where('user_id', $user->id);
    //     }

    //     if ($request->filled('device_detail_id') && $request->device_detail_id >0) {
    //         $query->where('device_id', $request->device_detail_id);
    //     }

    //     if ($request->filled('group_id') && $request->group_id >0) {
    //         $group = Devices::where('user_id', $user->id)
    //                             ->first();
    //         if ($group) {
    //             $deviceIds = json_decode($group->deviceIds, true) ?? [];
    //             $query->whereIn('device_id', $deviceIds);
    //         }
    //     }

    //     // Fetch paginated devices
    //     $all_Device = $query->offset($offset * $limit)->limit($limit)->orderBy('id', 'desc')->get();
    //     $nextDevice = $query->offset($offset2 * $limit)->limit($limit)->orderBy('id', 'desc')->get();
    //     $deviceIds = $all_Device->pluck('device_id')->toArray();

    //     // **Step 2: Fetch All Devices in One API Call**
    //     $veh_data = [];
    //     if (!empty($deviceIds)) {
    //         $getIds = implode("&id=", $deviceIds);
    //         $response = static::curl("/api/devices?id=" . $getIds, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //         $veh_data = json_decode($response->response, true) ?? [];
    //     }

    //     // **Step 3: Fetch All Positions in One API Call**
    //     $positionIds = collect($veh_data)->pluck('positionId')->unique()->filter()->toArray();
    //     $positionData = [];

    //     if (!empty($positionIds)) {
    //         $param = '/?id=' . implode("&id=", $positionIds);
    //         $positionResponse = static::curl("/api/positions" . $param, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //         $positionData = json_decode($positionResponse->response, true) ?? [];
    //     }

    //     // **Step 4: Map Positions to Devices**
    //     $positionMap = collect($positionData)->keyBy('id'); // Map positions by `positionId` for faster lookups
    //     // **Step 5: Loop to Format Data as Required**
    //     $devices = [];
    //     $geofence = app(GeofencesService::class);
    //     foreach ($veh_data as $device) {
    //         $deviceIndex = array_search($device['id'], $deviceIds);
    //         if ($deviceIndex === false) continue;

    //         $deviceInfo = $all_Device[$deviceIndex] ?? null;
    //         if (!$deviceInfo) continue;

    //         $index = count($devices);
    //         $position = $positionMap[$device['positionId']] ?? []; // Correctly get the position for each device
    //         $attributes = $position['attributes'] ?? [];

    //         $devices[$index] = [
    //             'id' => $deviceInfo->id,
    //             'user_id' => $deviceInfo->user_id,
    //             'device_id' => $device['id'],
    //             'name' => $device['name'] ?? null,
    //             'positionId' => $device['positionId'],
    //             'status' => $device['status'],
    //             'deviceType' => 'tracking server',
    //             'lastUpdate' => isset($device['lastUpdate'])
    //                 ? date('M d g:i a', strtotime($device['lastUpdate']))
    //                 : date('M d g:i a', strtotime($deviceInfo->updated_at)),

    //             'devicData' => array_merge($device, ['lastUpdate' => date('M d g:i a', strtotime($deviceInfo->updated_at))]),
    //             'device_settings' => json_decode($deviceInfo->device_data, true) ?? [],
    //             'positionData' => $position,
    //             'geofences' => isset($request->is_geofence) && $request->is_geofence
    //                 ? $geofence->deviceGeofences($request, $device['id']) ?? []
    //                 : [],
    //             'trips' => [],
    //             'totalDistance' => '0Km',
    //             'ignition' => $attributes['ignition'] ?? false,
    //             'attributes' => $device['attributes'] ?? [],
    //         ];

    //         // **Step 6: Fetch Trips if Needed**
    //         if (isset($request->device_trips) && $request->device_trips == true) {
    //             $tripResponse = static::curl("/api/reports/trips?deviceId={$device['id']}&from={$from}&to={$to}", 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //             $devices[$index]['trips'] = json_decode($tripResponse->response, true) ?? [];
    //         }

    //         // **Step 7: Format Total Distance**
    //         if (!empty($attributes['totalDistance'])) {
    //             $devices[$index]['totalDistance'] = number_format($attributes['totalDistance'] / 1000, 2) . 'Km';
    //         }

    //         // **Step 8: Ensure Position Data Time is Set**
    //         if (!empty($devices[$index]['positionData'])) {
    //             $devices[$index]['positionData']['time'] = $devices[$index]['lastUpdate'];
    //         }
    //     }
    //     return [
    //         'devices' => $devices,
    //         'nextDevice' => $nextDevice,
    //     ];
    // }

    /**
     * Build positions payload using Traccar API via local device mapping.
     * Mirrors getAllDevices approach (Traccar API), not direct DB joins.
     *
     * Options:
     * - mine (bool): restrict to user's own devices
     * - includeRaw (bool): include raw device and position arrays from Traccar
     * - source (string): payload discriminator (e.g., 'current' or 'updated')
     */
    public function getLiveDevices(User $user, array $options = []): array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $mine = (bool)($options['mine'] ?? false);
        $includeRaw = (bool)($options['includeRaw'] ?? false);
        $source = $options['source'] ?? null;

        // Scope devices for this user (mirror VehicleController role logic)
        // Note: Devices table does not have device_type; use user/distributor scoping only
        $query = Devices::query();

        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);
        if ($mine) {
            // Strictly the current user's assigned devices
            $query->where('user_id', $user->id);
        } else {
            if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
                // Distributor: both user_id and distributor_id must match self
                $query->where('user_id', $user->id)
                      ->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                // Non-admin (user/fleet manager): user_id must match; distributor scoped to user's distributor
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)
                      ->where('user_id', $user->id);
            }
            // Admin: see all devices; no additional where
        }

        $all_Device = $query->orderBy('id', 'desc')->get();
        $deviceIds = $all_Device->pluck('device_id')->toArray();

        // Fetch Traccar devices in one call
        $traccarDevices = [];
        if (!empty($deviceIds)) {
            $getIds = implode('&id=', $deviceIds);
            $response = static::curl('/api/devices?id=' . $getIds, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
            $traccarDevices = json_decode($response->response, true) ?? [];
        }

        // Fetch Traccar positions in one call
        $positionIds = collect($traccarDevices)->pluck('positionId')->unique()->filter()->toArray();
        $positionMap = collect();
        if (!empty($positionIds)) {
            $param = '/?id=' . implode('&id=', $positionIds);
            $positionResponse = static::curl('/api/positions' . $param, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
            $positions = json_decode($positionResponse->response, true) ?? [];
            $positionMap = collect($positions)->keyBy('id');
        }

        // Build compact devices-with-position payload
        $now = now();
        $positionsPayload = [];
        foreach ($traccarDevices as $device) {
            $position = $positionMap[$device['positionId']] ?? null;
            if (!$position) { continue; }

            $attrs = [];
            if (isset($position['attributes'])) {
                $attrs = is_array($position['attributes']) ? $position['attributes'] : (json_decode($position['attributes'], true) ?? []);
            }

            $serverTimeRaw = $position['serverTime'] ?? ($position['servertime'] ?? null);
            $serverTime = $serverTimeRaw ? \Carbon\Carbon::parse($serverTimeRaw) : null;
            $online = $serverTime ? $serverTime->gte($now->copy()->subHour()) : false;

            $motion = isset($attrs['motion']) ? (int)$attrs['motion'] : null;
            $ignition = $attrs['ignition'] ?? null;

            $activity = 'noData';
            if ($serverTime) {
                if ($serverTime->lt($now->copy()->subHour())) {
                    $activity = 'inActive';
                } else {
                    if ($motion === 1) {
                        $activity = 'moving';
                    } elseif ($ignition === 1 && $motion === 0) {
                        $activity = 'idle';
                    } elseif ($motion === 0 && $ignition === 0) {
                        $activity = 'stopped';
                    } else {
                        $activity = 'noData';
                    }
                }
            }

            $statusRaw = strtolower(trim((string)($device['status'] ?? 'unknown')));
            $deviceStatus = in_array($statusRaw, ['online','offline','unknown']) ? $statusRaw : 'unknown';

            $payload = [
                'id' => (int)$device['id'],
                'name' => $device['name'] ?? ('Device #' . $device['id']),
                'latitude' => $position['latitude'] ?? null,
                'longitude' => $position['longitude'] ?? null,
                'speed' => $position['speed'] ?? null,
                'address' => $position['address'] ?? null,
                'ignition' => $ignition,
                'status' => ($deviceStatus === 'online') ? 'online' : 'offline',
                'activity' => $activity,
                'motion' => $motion,
                'online' => $online,
                'positionId' => $device['positionId'] ?? null,
                'lastUpdate' => $device['lastUpdate'] ?? null,
                'uniqueId' => ($device['uniqueId'] ?? $device['uniqueid'] ?? null),
                'attributes' => $attrs,
                'serverTime' => $serverTimeRaw,
            ];

            if ($includeRaw) {
                $payload['device'] = $device;
                $payload['position'] = $position;
            }
            if ($source) {
                $payload['source'] = $source;
            }

            // Filter out entries missing coordinates
            if ($payload['latitude'] !== null && $payload['longitude'] !== null) {
                $positionsPayload[] = $payload;
            }
        }

        return $positionsPayload;
    }

    /**
     * Fetch a single device from Traccar with its latest position and trips history.
     *
     * Input options:
     * - from (string|int): start time (parseable string or timestamp). Defaults to now - 24h.
     * - to (string|int): end time (parseable string or timestamp). Defaults to now.
     * - includeRaw (bool): include raw Traccar device/position payloads.
     *
     * Returns a compact payload similar to live tracking, plus trips.
     */
    public function getDeviceDetailWithTrips(User $user, int $deviceId, array $options = []): array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $includeRaw = (bool)($options['includeRaw'] ?? false);

        // Resolve time window
        $toIso = null;
        $fromIso = null;
        if (isset($options['to'])) {
            $toIso = gmdate('Y-m-d\TH:i:00\Z', is_numeric($options['to']) ? (int)$options['to'] : strtotime((string)$options['to']));
        } else {
            $toIso = gmdate('Y-m-d\TH:i:00\Z');
        }
        if (isset($options['from'])) {
            $fromIso = gmdate('Y-m-d\TH:i:00\Z', is_numeric($options['from']) ? (int)$options['from'] : strtotime((string)$options['from']));
        } else {
            $fromIso = gmdate('Y-m-d\TH:i:00\Z', strtotime('-1 day'));
        }

        // Fetch device from Traccar
        $deviceResp = static::curl('/api/devices?id=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $devices = json_decode($deviceResp->response, true) ?? [];
        $device = $devices[0] ?? null;
        if (!$device) {
            return [];
        }

        // Fetch position if available
        $position = null;
        if (!empty($device['positionId'])) {
            $posResp = static::curl('/api/positions/?deviceId=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
            $posList = json_decode($posResp->response, true) ?? [];
            $position = $posList[0] ?? null;
        }

        // Normalize attributes
        $attrs = [];
        if (isset($position['attributes'])) {
            $attrs = is_array($position['attributes']) ? $position['attributes'] : (json_decode($position['attributes'], true) ?? []);
        }

        $now = now();
        $serverTimeRaw = $position['serverTime'] ?? ($position['servertime'] ?? null);
        $serverTime = $serverTimeRaw ? \Carbon\Carbon::parse($serverTimeRaw) : null;
        $online = $serverTime ? $serverTime->gte($now->copy()->subHour()) : false;
        $motion = isset($attrs['motion']) ? (int)$attrs['motion'] : null;
        $ignition = $attrs['ignition'] ?? null;
        $activity = 'noData';
        if ($serverTime) {
            if ($serverTime->lt($now->copy()->subHour())) {
                $activity = 'inActive';
            } else {
                if ($motion === 1) {
                    $activity = 'moving';
                } elseif ($ignition === 1 && $motion === 0) {
                    $activity = 'idle';
                } elseif ($motion === 0 && $ignition === 0) {
                    $activity = 'stopped';
                } else {
                    $activity = 'noData';
                }
            }
        }

        // Build raw payload for frontend to compute display logic
        $payload = [
            'device' => $device,
            'position' => $position,
            'from' => $fromIso,
            'to' => $toIso,
        ];

        // Fetch trips history
        $tripsResp = static::curl('/api/reports/trips?deviceId=' . $deviceId . '&from=' . $fromIso . '&to=' . $toIso, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $trips = json_decode($tripsResp->response, true) ?? [];

        $driverResp = static::curl('/api/drivers?deviceId='.$deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $drivers = json_decode($driverResp->response, true) ?? [];
        $payload['trips'] = $trips;
        $payload['drivers'] = $drivers;

        return $payload;
    }

    /**
     * Return raw Traccar device for the given deviceId.
     */
    public function getDeviceRaw(User $user, int $deviceId): ?array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $resp = static::curl('/api/devices?id=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $list = json_decode($resp->response, true) ?? [];
        return isset($list[0]) ? $list[0] : null;
    }

    /**
     * Return the current (latest) position for a device using its positionId.
     */
    public function getCurrentPosition(User $user, int $deviceId): ?array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $deviceResp = static::curl('/api/devices?id=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $devices = json_decode($deviceResp->response, true) ?? [];
        $device = $devices[0] ?? null;
        $positionId = (int) ($device['positionId'] ?? 0);
        if ($positionId <= 0) return null;
        $posResp = static::curl('/api/positions/?deviceId=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        $positions = json_decode($posResp->response, true) ?? [];
        return isset($positions[0]) ? $positions[0] : null;
    }

    /**
     * Return trips for a device over a time window.
     * Options: from, to (string|int)
     */
    public function getTrips(User $user, int $deviceId, array $options = []): array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $toIso = isset($options['to'])
            ? gmdate('Y-m-d\TH:i:00\Z', is_numeric($options['to']) ? (int)$options['to'] : strtotime((string)$options['to']))
            : gmdate('Y-m-d\TH:i:00\Z');
        $fromIso = isset($options['from'])
            ? gmdate('Y-m-d\TH:i:00\Z', is_numeric($options['from']) ? (int)$options['from'] : strtotime((string)$options['from']))
            : gmdate('Y-m-d\TH:i:00\Z', strtotime('-1 day'));
        $resp = static::curl('/api/reports/trips?deviceId=' . $deviceId . '&from=' . $fromIso . '&to=' . $toIso, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        return json_decode($resp->response, true) ?? [];
    }

    /**
     * Return all drivers assigned to the device from tracking server.
     */
    public function getDriversForDevice(User $user, int $deviceId): array
    {
        $sessionId = $user->traccarSession ?? session('cookie');
        $resp = static::curl('/api/drivers?deviceId=' . $deviceId, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        return json_decode($resp->response, true) ?? [];
    }

    // public function getDeviceDetails($request)
    // {
    //     $id = session('tc_user_id');
    //     $sessionId = $request->user()->traccarSession ?? session('cookie');

    //     $offset = $request->input('offset', 0);
    //     $limit = $request->input('limit', 10);
    //     $offset2 = $offset + 1;

    //     $date = now();
    //     $from = $date->subMonths(6)->format('Y-m-d\TH:i:s\Z');
    //     $to = now()->format('Y-m-d\TH:i:s\Z');

    //     $user = $request->user();
    //     if (!$user) {
    //         return ['devices' => [], 'nextDevice' => []];
    //     }

    //     // **Step 1: Fetch Relevant Devices Efficiently**
    //     $query = Devices::where('device_type', 0);

    //     if ($user->user_role == 0) {
    //         $query->where('user_id', $user->id);
    //     }

    //     if ($request->filled('device_detail_id') && $request->device_detail_id >0) {
    //         $query->where('device_id', $request->device_detail_id);
    //     }

    //     if ($request->filled('group_id') && $request->group_id >0) {
    //         $group = DeviceGroup::where('groupId', $request->group_id)
    //                             ->where('user_id', $user->id)
    //                             ->first();
    //         if ($group) {
    //             $deviceIds = json_decode($group->deviceIds, true) ?? [];
    //             $query->whereIn('device_id', $deviceIds);
    //         }
    //     }

    //     // Fetch paginated devices
    //     $all_Device = $query->offset($offset * $limit)->limit($limit)->orderBy('id', 'desc')->get();
    //     $nextDevice = $query->offset($offset2 * $limit)->limit($limit)->orderBy('id', 'desc')->get();
    //     $deviceIds = $all_Device->pluck('device_id')->toArray();

    //     // **Step 2: Fetch All Devices in One API Call**
    //     $veh_data = [];
    //     if (!empty($deviceIds)) {
    //         $getIds = implode("&id=", $deviceIds);
    //         $response = static::curl("/api/devices?id=" . $getIds, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //         $veh_data = json_decode($response->response, true) ?? [];
    //     }

    //     // **Step 3: Fetch All Positions in One API Call**
    //     $positionIds = collect($veh_data)->pluck('positionId')->unique()->filter()->toArray();
    //     $positionData = [];

    //     if (!empty($positionIds)) {
    //         $param = '/?id=' . implode("&id=", $positionIds);
    //         $positionResponse = static::curl("/api/positions" . $param, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //         $positionData = json_decode($positionResponse->response, true) ?? [];
    //     }

    //     // **Step 4: Map Positions to Devices**
    //     $positionMap = collect($positionData)->keyBy('id'); // Map positions by `positionId` for faster lookups
    //     // **Step 5: Loop to Format Data as Required**
    //     $devices = [];
    //     $geofence = app(GeofencesService::class);
    //     foreach ($veh_data as $device) {
    //         $deviceIndex = array_search($device['id'], $deviceIds);
    //         if ($deviceIndex === false) continue;

    //         $deviceInfo = $all_Device[$deviceIndex] ?? null;
    //         if (!$deviceInfo) continue;

    //         $index = count($devices);
    //         $position = $positionMap[$device['positionId']] ?? []; // Correctly get the position for each device
    //         $attributes = $position['attributes'] ?? [];
    //         $devices[$index] = array_merge($device,
    //         [
    //             'user_id' => $deviceInfo->user_id,
    //             'lastUpdate' => isset($device['lastUpdate'])
    //                 ? date('M d g:i a', strtotime($device['lastUpdate']))
    //                 : date('M d g:i a', strtotime($deviceInfo->updated_at)),
    //             'totalDistance' => '0Km',
    //             'ignition' => $attributes['ignition'] ?? false,
    //             'positionData' => $position,

    //         ]);


    //         // **Step 6: Fetch Trips if Needed**
    //         if (isset($request->device_trips) && $request->device_trips == true) {
    //             $tripResponse = static::curl("/api/reports/trips?deviceId={$device['id']}&from={$from}&to={$to}", 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
    //             $devices[$index]['trips'] = json_decode($tripResponse->response, true) ?? [];
    //         }

    //         // **Step 7: Format Total Distance**
    //         if (!empty($attributes['totalDistance'])) {
    //             $devices[$index]['totalDistance'] = number_format($attributes['totalDistance'] / 1000, 2) . 'Km';
    //         }

    //         // **Step 8: Ensure Position Data Time is Set**
    //         if (!empty($devices[$index]['positionData'])) {
    //             $devices[$index]['positionData']['time'] = $devices[$index]['lastUpdate'];
    //         }
    //     }
    //     return $devices;
    // }


    // public function syncDevicesLocalDb($request)
    // {
    //     $sessionId = $request->user()->traccarSession ?? session('cookie');
    //     $data = static::curl('/api/devices', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
    //     $veh_data = json_decode($data->response);
    //     if (!empty($veh_data)) {
    //         $admin = User::where('user_role', 1)->first();
    //         foreach ($veh_data as $key => $device) {
    //             if (!empty($device)) {
    //                 $userId = $admin->id;
    //                 if (isset($device->attributes->user_id)) {
    //                     $userId = $device->attributes->user_id;
    //                 }
    //                 $device = Devices::updateOrCreate([
    //                     'device_id' => $device->id,
    //                     'device_modal' => $device->name,
    //                     'user_id' => $userId,
    //                     'device_data' => json_encode($device->attributes),
    //                     'device_type' => 0,
    //                 ]);
    //             }
    //         }
    //     }
    // }

    public function getDeviceByID($device_id, $request = null, $session = null, )
    {

        $id = $device_id;

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        if ($id != '') {
            $data = '?id=' . $id;
        }
        $data = static::curl('/api/devices' . $data, 'GET', $sessionId, '', array());
        $veh_data = json_decode($data->response);
        $result = [];
        if (isset($veh_data[0]) && isset($veh_data[0]->id)) {
            $result[0] = $veh_data[0];
            if ($veh_data[0]->positionId > 0) {
                $param = '/?id=' . $veh_data[0]->positionId;
                $position = static::curl('/api/positions' . $param, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
                $position_data = json_decode($position->response);
                $result[0]->positionData = isset($position_data[0]) ? $position_data[0] : [];
            }
        }
        return $result;
    }


    // public function getAllDevicesStatus($request)
    // {
    //     $sessionId = $request->user()->traccarSession ?? session('cookie');
    //     $user = $request->user();
    //     $getIds = '';
    //     $deviceIds = [];
    //     if (!empty($user)) {
    //         if ($user->user_role == 0) {
    //             $all_Device = Devices::where(['user_id' => $user->id, 'device_type' => 0]);
    //         } else {
    //             $all_Device = Devices::where('device_type', 0);
    //         }
    //         $all_Device = $all_Device->orderBy('id', 'desc')->get();
    //         $deviceIds = $all_Device->pluck('device_id');
    //         $deviceIds = $deviceIds->toArray();
    //     }
    //     if (!empty($deviceIds)) {
    //         foreach ($deviceIds as $key => $value) {
    //             $getIds .= "id=$value&";
    //         }
    //     }
    //     $devices = [];
    //     if ($getIds !== "") {
    //         $data = static::curl('/api/devices?' . $getIds, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
    //         $devices = json_decode($data->response);
    //     }
    //     $count['offline'] = 0;
    //     $count['online'] = 0;
    //     $count['unknown'] = 0;
    //     $count['blocked'] = 0;
    //     $count['noData'] = 0;
    //     $count['parked'] = 0;
    //     $count['immobilize'] = 0;
    //     $count['totalDevices'] = ($devices != null) ? count($devices) : 0;
    //     if ($devices) {
    //         for ($i = 0; $i < count($devices); $i++) {
    //             if (isset($devices[$i]) && isset($all_Device[array_search($devices[$i]->id, $deviceIds)])) {
    //                 if ($devices[$i]->status == 'unknown') {
    //                     $count['unknown']++;
    //                 }
    //                 if ($devices[$i]->status == 'online') {
    //                     $count['online']++;
    //                 }
    //                 if ($devices[$i]->status == 'offline') {
    //                     $count['offline']++;
    //                 }
    //                 if ($devices[$i]->status == 1) {
    //                     $count['blocked']++;
    //                 }
    //                 if ($devices[$i]->positionId == 0 || $devices[$i]->positionId == null) {
    //                     $count['noData']++;
    //                 }
    //                 if (isset($devices[$i]->attributes->is_parked) && $devices[$i]->attributes->is_parked == 1) {
    //                     $count['parked']++;
    //                 }
    //                 if (isset($devices[$i]->attributes->immobilize) && $devices[$i]->attributes->immobilize == 1) {
    //                     $count['immobilize']++;
    //                 }
    //             }
    //         }
    //     }

    //     return $count;
    // }

    public function getDevicesByStatus($filter, $request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $position_response = static::curl('/api/positions', 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $position_response = json_decode($position_response->response);
        $id = session('tc_user_id');
        $data = 'id=' . $id;
        $devices = static::curl('/api/devices?all=true' . $data, 'GET', $sessionId, '', array());
        $devices = json_decode($devices->response);

        $i = 0;
        $check = 0;
        $response = [];
        $count['totalDevices'] = count($devices);

        foreach ($devices as $key => $device) {
            if ($filter == "blocked") {
                if ($device->disabled == 1) {
                    $check = 1;
                    $response[$i]['id'] = isset($device->id) ? $device->id : null;
                    $response[$i]['name'] = $device->name ?? isset($device->name) ? $device->name : null;
                    $response[$i]['category'] = isset($device->category) ? $device->category : null;
                    $response[$i]['lastUpdate'] = $device->lastUpdate ? Helpers::servertime($device->lastUpdate) : '';
                    $response[$i]['speed'] = "-";
                    $response[$i]['address'] = "-";
                    $response[$i]['battery'] = "-";
                    $response[$i]['status'] = "blocked";
                    $response[$i]['device_status'] = isset($device->status) ? $device->status : "-";
                }
            }
            if ($filter == "allDevices") {
                if ($device->disabled != 1) {
                    $check = 1;
                    $response[$i]['id'] = isset($device->id) ? $device->id : null;
                    $response[$i]['name'] = $device->name ?? isset($device->name) ? $device->name : null;
                    $response[$i]['category'] = isset($device->category) ? $device->category : null;
                    $response[$i]['lastUpdate'] = $device->lastUpdate ? Helpers::servertime($device->lastUpdate) : '';
                    $response[$i]['speed'] = "-";
                    $response[$i]['address'] = "-";
                    $response[$i]['battery'] = "-";
                    $response[$i]['device_status'] = isset($device->status) ? $device->status : "-";

                    if ($device->positionId == 0 || $device->positionId == null) {
                        $response[$i]['status'] = "noData";
                    } elseif ($device->disabled == 1) {
                        $response[$i]['status'] = "blocked";
                    } elseif (isset($device->attributes->is_parked) && $device->attributes->is_parked == 1) {
                        $response[$i]['status'] = "parked";
                    } elseif (isset($device->attributes->immobilize) && $device->attributes->immobilize == 1) {
                        $response[$i]['status'] = "immobilize";
                    } else {
                        $response[$i]['status'] = "noData";
                    }

                }
            }
            if ($filter == "noData") {
                if ($device->positionId == 0 || $device->positionId == null) {
                    $check = 1;
                    $response[$i]['id'] = isset($device->id) ? $device->id : null;
                    $response[$i]['name'] = $device->name ?? isset($device->name) ? $device->name : null;
                    $response[$i]['category'] = isset($device->category) ? $device->category : null;
                    $response[$i]['lastUpdate'] = $device->lastUpdate ? Helpers::servertime($device->lastUpdate) : '';
                    $response[$i]['speed'] = "-";
                    $response[$i]['address'] = "-";
                    $response[$i]['battery'] = "-";
                    $response[$i]['gsm'] = "-";
                    $response[$i]['status'] = "noData";
                    $response[$i]['device_status'] = isset($device->status) ? $device->status : "-";
                }
            }
            if ($filter == "parked") {
                if (isset($device->attributes->is_parked) && $device->attributes->is_parked == 1) {
                    $check = 1;
                    $response[$i]['id'] = isset($device->id) ? $device->id : null;
                    $response[$i]['name'] = $device->name ?? isset($device->name) ? $device->name : null;
                    $response[$i]['category'] = isset($device->category) ? $device->category : null;
                    $response[$i]['lastUpdate'] = $device->lastUpdate ? Helpers::servertime($device->lastUpdate) : '';
                    $response[$i]['status'] = "parked";
                    $response[$i]['device_status'] = isset($device->status) ? $device->status : "-";
                    $engin = json_decode(json_encode($device->attributes), true);
                    $response[$i]['Engine'] = (explode('/', $engin['Engine/Chasis'])[0]);
                    $chasis = json_decode(json_encode($device->attributes), true);
                    $response[$i]['Chasis'] = (explode('/', $chasis['Engine/Chasis'])[1]);
                }
            }
            if ($filter == "immobilize") {
                if (isset($device->attributes->immobilize) && $device->attributes->immobilize == 1) {
                    $check = 1;
                    $response[$i]['id'] = isset($device->id) ? $device->id : null;
                    $response[$i]['name'] = $device->name ?? isset($device->name) ? $device->name : null;
                    $response[$i]['category'] = isset($device->category) ? $device->category : null;
                    $response[$i]['lastUpdate'] = $device->lastUpdate ? Helpers::servertime($device->lastUpdate) : '';
                    $response[$i]['status'] = "immobilize";
                    $response[$i]['device_status'] = isset($device->status) ? $device->status : "-";
                }
            }
            if ($check == 1) {
                foreach ($position_response as $key => $position) {
                    if ($device->id == $position->deviceId) {
                        $response[$i]['speed'] = isset($position->speed) ? $position->speed : null;
                        $response[$i]['address'] = isset($position->address) ? $position->address : null;
                        $response[$i]['battery'] = isset($position->attributes->batteryLevel) ? $position->attributes->batteryLevel : "-";
                        $response[$i]['ignition'] = isset($position->attributes->ignition) ? $position->attributes->ignition : null;
                        $response[$i]['gpsStatus'] = isset($position->attributes->sat) ? $position->attributes->sat : "-";
                        $response[$i]['serverTime'] = isset($position->serverTime) ? Helpers::servertime($position->serverTime) : '';
                        if ($filter == "allDevices") {
                            if (isset($position->attributes->motion) && $position->attributes->motion == 1 && (strtotime($position->serverTime) >= strtotime('-1 hour'))) {
                                $response[$i]['status'] = "moving";
                            } elseif (isset($position->attributes->ignition) && $position->attributes->ignition == 1 && $position->attributes->motion == 0 && (strtotime($position->serverTime) >= strtotime('-1 hour'))) {
                                $response[$i]['status'] = "idle";
                            } else if (isset($position->attributes->ignition) && $position->attributes->motion == 0 && $position->attributes->ignition == 0 && (strtotime($position->serverTime) >= strtotime('-1 hour'))) {
                                $response[$i]['status'] = "stopped";
                            } elseif (isset($position->attributes->alarm) && $position->attributes->alarm == "overspeed" && $position->attributes->motion == 1 && (strtotime($position->serverTime) >= strtotime('-1 hour'))) {
                                $response[$i]['status'] = "overSpeed";
                            } elseif (strtotime($position->serverTime) <= strtotime('-1 hour')) {
                                $response[$i]['status'] = "inActive";
                            }
                        }
                    }
                }
                $response[$i]['device_detail'] = $device->id;
                $i++;
                $check = 0;
            }
        }
        return $response;
    }
    public static function deviceAdd($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');

        // Build sanitized payload with only supported Traccar fields
        $allowed = [
            'id', 'name', 'uniqueId', 'phone', 'model', 'category',
            'groupId', 'calendarId', 'contact', 'disabled', 'expirationTime', 'attributes'
        ];

        $input = $request->all();
        if (isset($input['deviceInfo']) && is_array($input['deviceInfo'])) {
            $input = $input['deviceInfo'];
        }

        // Normalize attributes to array/object
        if (isset($input['attributes']) && is_string($input['attributes'])) {
            $decoded = json_decode($input['attributes'], true);
            $input['attributes'] = is_array($decoded) ? $decoded : [];
        }

        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key];
            }
        }

        // Ensure id is set for POST if needed
        if (!isset($data['id'])) {
            $data['id'] = -1;
        }

        $dataValue = json_encode($data);
        return self::curl('/api/devices', 'POST', $sessionId, $dataValue, ['Content-Type: application/json', 'Accept: ' . 'application/json']);
    }

    public function deviceUpdate($request)
    {
        $input = $request->all();
        if (isset($input['deviceInfo']) && is_array($input['deviceInfo'])) {
            $input = $input['deviceInfo'];
        }

        // Normalize attributes to array/object
        if (isset($input['attributes']) && is_string($input['attributes'])) {
            $decoded = json_decode($input['attributes'], true);
            $input['attributes'] = is_array($decoded) ? $decoded : [];
        }

        // Whitelist supported fields
        $allowed = ['id','name','uniqueId','phone','model','category','groupId','calendarId','contact','disabled','expirationTime','attributes'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key];
            }
        }

        $id = $data['id'] ?? null;
        $dataValue = json_encode($data);
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return self::curl('/api/devices/' . $id, 'PUT', $sessionId, $dataValue, ['Content-Type: application/json', 'Accept: application/json']);
    }

    public static function deviceDelete($request)
    {
        $id = $request->device_detail_id;
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return self::curl('/api/devices/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
    }

    public static function mileageUpdate($id, $mileage, $speedlimit, $request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $userResponse = static::curl('/api/devices/' . $id, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $resp = $userResponse = json_decode($userResponse->response);
        $id = $resp->id;
        $attributes = $resp->attributes;
        $name = $resp->name;
        $uniqueId = $resp->uniqueId;
        $phone = $resp->phone;
        $model = $resp->model;
        $category = $resp->category;
        $attributes->mileage = $mileage;
        $attributes->speed_Limit = $speedlimit;
        $attributes = json_encode($attributes);
        $data = '{"id":"' . $id . '","name":"' . $name . '","uniqueId":"' . $uniqueId . '","phone":"' . $phone . '","model":"' . $model . '","category":"' . $category . '","attributes":' . $attributes . '}';
        $response = self::curl('/api/devices/' . $id, 'PUT', $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
        return $response;
    }
    public static function speedlimitUpdate($id, $speed_Limit, $request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $userResponse = static::curl('/api/devices/' . $id, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $resp = $userResponse = json_decode($userResponse->response);
        $id = $resp->id;
        $attributes = $resp->attributes;
        $name = $resp->name;
        $uniqueId = $resp->uniqueId;
        $phone = $resp->phone;
        $model = $resp->model;
        $category = $resp->category;
        $attributes->speed_Limit = $speed_Limit;
        $attributes = json_encode($attributes);
        $data = '{"id":"' . $id . '","name":"' . $name . '","uniqueId":"' . $uniqueId . '","phone":"' . $phone . '","model":"' . $model . '","category":"' . $category . '","attributes":' . $attributes . '}';
        $resp = self::curl('/api/devices/' . $id, 'PUT', $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
        return $resp;
    }

}

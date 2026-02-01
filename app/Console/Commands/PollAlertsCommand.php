<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Events\AlertsUpdated;
use App\Models\User;
use Carbon\Carbon;

class PollAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll database for new alerts and broadcast them';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting alert polling service...');

        // Initialize last_id. Priority: Cache > DB Max > 0
        // In production, using Cache ensures we don't miss alerts during restarts.
        $dbMaxId = DB::connection('pgsql')->table('tc_events')->max('id') ?? 0;
        $lastId = Cache::get('alerts_poll_last_id', $dbMaxId);

        // Safety check: If cache is too old (e.g., > 1000 events behind), skip to current to avoid massive flood
        if ($dbMaxId - $lastId > 1000) {
            $this->warn("Last ID {$lastId} is too far behind DB Max {$dbMaxId}. Skipping to DB Max to avoid flood.");
            $lastId = $dbMaxId;
        }

        $this->info("Initial Last ID: {$lastId}");

        while (true) {
            try {
                // Fetch new events using Eloquent and strict notification logic
                $newEvents = \App\Models\TcEvent::where('id', '>', $lastId)
                    ->withEnabledNotifications()
                    ->distinct('id')
                    ->orderBy('id', 'asc')
                    ->limit(50)
                    ->get();

                if ($newEvents->isNotEmpty()) {
                    $affectedUserIds = [];
                    $maxId = $lastId;

                    foreach ($newEvents as $event) {
                        $this->info("Processing event ID: {$event->id} - Type: {$event->type}");

                        // Find user linked to this device
                        // using Devices model to find the owner/manager
                        $device = \App\Models\Devices::where('device_id', $event->deviceid)->first();
                        $userId = $device ? $device->user_id : null;

                        if ($userId) {
                            $affectedUserIds[$userId] = true;
                        }

                        if ($event->id > $maxId) {
                            $maxId = $event->id;
                        }

                        // Check for frequent ignition
                        if ($event->type === 'ignitionOn') {
                            $this->checkFrequentIgnition($event);
                        }
                    }

                    // Broadcast to affected users
                    foreach (array_keys($affectedUserIds) as $uid) {
                        $user = User::find($uid);
                        if ($user) {
                             $this->info("Dispatching AlertsUpdated for User ID: {$uid}");
                             broadcast(new AlertsUpdated($user));
                        }
                    }

                    // Update lastId
                    $lastId = $maxId;
                    Cache::put('alerts_poll_last_id', $lastId, now()->addDay());
                }

                // Sleep to prevent high CPU usage
                sleep(2);

            } catch (\Exception $e) {
                $this->error("Error polling alerts: " . $e->getMessage());
                sleep(5); // Sleep longer on error
            }
        }
    }

    protected function checkFrequentIgnition($event)
    {
        $deviceId = $event->deviceid;
        $key = "device_ignition_log:{$deviceId}";
        $lockKey = "device_frequent_ignition_alerted:{$deviceId}";

        // 1. Get history
        $history = Cache::get($key, []);

        // 2. Add current time (use eventtime from event to be accurate)
        $eventTime = $event->eventtime;
        if (!$eventTime) {
            $eventTime = now();
        }

        $history[] = $eventTime->timestamp;

        // 3. Prune old (e.g., older than 5 mins)
        // User said "preventing the driver start for a while and off repeatedly for air-conditioning use"
        // This implies rapid cycling. 5 minutes seems reasonable.
        $windowSeconds = 10 * 60;
        $threshold = 4; // 4 times in 5 mins

        $history = array_filter($history, function ($ts) use ($eventTime, $windowSeconds) {
            return ($eventTime->timestamp - $ts) <= $windowSeconds;
        });

        // Re-index array
        $history = array_values($history);

        Cache::put($key, $history, now()->addMinutes(30));

        // 4. Check Threshold
        if (count($history) >= $threshold) {
            // Check if we recently alerted
            if (!Cache::has($lockKey)) {
                // TRIGGER ALERT
                $this->createFrequentIgnitionEvent($event);

                // Set cooldown (e.g., 30 mins)
                Cache::put($lockKey, true, now()->addMinutes(30));
            }
        }
    }

    protected function createFrequentIgnitionEvent($triggerEvent)
    {
        try {
            $alert = new \App\Models\TcEvent();
            $alert->type = 'frequentIgnition';
            $alert->servertime = now();
            $alert->eventtime = now();
            $alert->deviceid = $triggerEvent->deviceid;
            $alert->positionid = $triggerEvent->positionid;
            $alert->geofenceid = null;
            // Use attributes JSON field
            $alert->attributes = ['message' => 'Frequent ignition detected: Driver is cycling ignition repeatedly.'];
            $alert->maintenanceid = null;
            $alert->save();

            $this->info("Generated frequentIgnition event for device {$triggerEvent->deviceid}");
        } catch (\Exception $e) {
            $this->error("Failed to create frequentIgnition event: " . $e->getMessage());
        }
    }
}

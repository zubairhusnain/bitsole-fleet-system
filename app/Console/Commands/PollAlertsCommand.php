<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Events\NewAlertEvent;
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
                // Fetch new events with relaxed join logic to include 'always' notifications
                $newEvents = DB::connection('pgsql')->table('tc_events as e')
                    ->join('tc_devices as d', 'e.deviceid', '=', 'd.id')
                    ->join('tc_notifications as n', 'e.type', '=', 'n.type')
                    ->leftJoin('tc_device_notification as dn', function($join) {
                        $join->on('e.deviceid', '=', 'dn.deviceid')
                             ->on('n.id', '=', 'dn.notificationid');
                    })
                    ->select(
                        'e.*',
                        'n.attributes as notification_attributes',
                        'n.type as notification_type',
                        'd.name as device_name'
                    )
                    ->where('e.id', '>', $lastId)
                    ->where(function($query) {
                        $query->whereNotNull('dn.deviceid')
                              ->orWhere('n.always', true);
                    })
                    ->distinct('e.id')
                    ->orderBy('e.id', 'asc')
                    ->limit(50) // Batch limit
                    ->get();

                if ($newEvents->isNotEmpty()) {
                    foreach ($newEvents as $event) {
                        $this->info("Broadcasting event ID: {$event->id} - Type: {$event->type}");

                        // Broadcast the event
                        broadcast(new NewAlertEvent($event));

                        // Update lastId
                        $lastId = $event->id;
                        Cache::put('alerts_poll_last_id', $lastId, now()->addDay());
                    }
                }

                // Sleep to prevent high CPU usage
                sleep(2);

            } catch (\Exception $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Connection refused')) {
                    $this->error("Error: Cannot connect to Reverb server. Is it running?");
                    $this->error("Run: php artisan reverb:start");
                } else {
                    $this->error("Error polling alerts: " . $msg);
                }
                sleep(5); // Sleep longer on error
            }
        }

        return 0;
    }
}

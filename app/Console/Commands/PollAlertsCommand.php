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

        // Initialize last_id to the current max id to avoid broadcasting old events on restart
        // If cache exists, use it (optional, but safer to start fresh or from max to avoid flood)
        // For this implementation, we'll start from current max to avoid spamming on restart.
        $lastId = DB::connection('pgsql')->table('tc_events')->max('id') ?? 0;
        
        $this->info("Initial Last ID: {$lastId}");

        while (true) {
            try {
                // Fetch new events with the same join logic as NotificationController
                $newEvents = DB::connection('pgsql')->table('tc_events as e')
                    ->join('tc_devices as d', 'e.deviceid', '=', 'd.id')
                    // We join tc_device_notification and tc_notifications to ensure we only pick up relevant alerts
                    // Note: The original controller query started from tc_device_notification. 
                    // Here we start from tc_events to efficiently filter by ID > $lastId.
                    ->join('tc_device_notification as dn', 'e.deviceid', '=', 'dn.deviceid')
                    ->join('tc_notifications as n', function($join) {
                        $join->on('dn.notificationid', '=', 'n.id')
                             ->on('e.type', '=', 'n.type');
                    })
                    ->select(
                        'e.*', 
                        'n.attributes as notification_attributes', 
                        'n.type as notification_type', 
                        'd.name as device_name'
                    )
                    ->where('e.id', '>', $lastId)
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
                    }
                }

                // Sleep to prevent high CPU usage
                sleep(2); 

            } catch (\Exception $e) {
                $this->error("Error polling alerts: " . $e->getMessage());
                sleep(5); // Sleep longer on error
            }
        }

        return 0;
    }
}

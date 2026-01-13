<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Traits\GeocodingTrait;

class BackfillAddresses extends Command
{
    use GeocodingTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'traccar:backfill-addresses {--limit=10} {--device_id=} {--continuous}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing addresses in tc_positions table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $deviceId = $this->option('device_id');
        $continuous = $this->option('continuous');

        do {
            $msg = "Checking for missing addresses (Batch Limit: $limit)";
            if ($deviceId) {
                $msg .= " for Device ID: $deviceId";
            }
            $this->info($msg . "...");

            // Fetch positions with missing address
            // Order by ID descending to fix newest entries first
            $query = DB::connection('pgsql')->table('tc_positions')
                ->select('id', 'latitude', 'longitude')
                ->whereNull('address')
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0);

            if ($deviceId) {
                $query->where('deviceid', $deviceId);
            }

            $positions = $query->orderBy('id', 'desc')
                ->limit($limit)
                ->get();

            if ($positions->isEmpty()) {
                $this->info("No positions found with missing addresses.");
                break;
            }

            $this->info("Found " . $positions->count() . " positions. Processing...");

            $bar = $this->output->createProgressBar($positions->count());
            $bar->start();

            foreach ($positions as $pos) {
                $address = $this->getAddress($pos->latitude, $pos->longitude);

                if (!empty($address)) {
                    DB::connection('pgsql')->table('tc_positions')
                        ->where('id', $pos->id)
                        ->update(['address' => $address]);
                }

                // Respect Nominatim Rate Limit (1 req/sec)
                // Sleep 1.5s to be safe
                usleep(1500000);

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if ($continuous) {
                $this->info("Batch completed. Continuing to next batch...");
            }

        } while ($continuous);

        $this->info("Done.");

        return 0;
    }
}

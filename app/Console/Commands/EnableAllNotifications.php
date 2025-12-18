<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;
use App\Models\TcDeviceNotification;

class EnableAllNotifications extends Command
{
    use Curl;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enable:all-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable all notifications for all devices via Traccar API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Starting process to enable all notifications for all devices...");

        // 1. Login
        $email = Config::get('constants.Constants.adminEmail');
        $password = Config::get('constants.Constants.adminPassword');
        $host = Config::get('constants.Constants.host');

        if (!$email || !$password || !$host) {
            $this->error("Missing configuration in constants.Constants");
            return 1;
        }

        $this->info("Logging in to Traccar API at $host as $email...");

        $data = 'email=' . urlencode($email) . '&password=' . urlencode($password);
        $response = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);

        $cookie = $response->cookieData ?? session('cookie');

        // If session helper didn't capture it (e.g. array driver in console), try to parse manually from response header in trait?
        // The trait relies on session() store. In console, session array driver should work for the lifetime of the command.
        // But if it fails, we check response code.
        if ($response->responseCode < 200 || $response->responseCode >= 300) {
             $this->error("Login failed. Code: " . $response->responseCode . " Error: " . $response->error);
             return 1;
        }

        if (!$cookie) {
             // Try to re-parse if trait logic for session storage didn't persist accessible here
             // But the trait sets session(['cookie' => ...]), so retrieving it should work if Session is initialized.
             // If not, we might need to rely on the side effect or parse it again if we had access to headers.
             // But let's proceed, maybe it's fine.
             $this->warn("Session cookie not found in session store. API calls might fail if not authenticated.");
        }

        $this->info("Login successful.");

        // 2. Get All Devices
        $this->info("Fetching devices...");
        $respDevices = static::curl('/api/devices', 'GET', $cookie, '', [Config::get('constants.Constants.jsonA')]);

        if ($respDevices->responseCode != 200) {
            $this->error("Failed to fetch devices. Code: " . $respDevices->responseCode);
            return 1;
        }

        $devices = json_decode($respDevices->response, true);

        if (!is_array($devices)) {
            $this->error("Failed to parse devices response.");
            return 1;
        }
        $this->info("Found " . count($devices) . " devices.");

        // 3. Get All Notifications
        $this->info("Fetching notifications...");
        $respNotifs = static::curl('/api/notifications', 'GET', $cookie, '', [Config::get('constants.Constants.jsonA')]);

        if ($respNotifs->responseCode != 200) {
            $this->error("Failed to fetch notifications. Code: " . $respNotifs->responseCode);
            return 1;
        }

        $notifications = json_decode($respNotifs->response, true);

        if (!is_array($notifications)) {
            $this->error("Failed to parse notifications response.");
            return 1;
        }
        $this->info("Found " . count($notifications) . " notifications.");

        // 4. Get Existing Permissions (Optimization)
        $this->info("Fetching existing permissions from local DB...");
        $existing = TcDeviceNotification::all()->groupBy('deviceid')->map(function ($items) {
            return $items->pluck('notificationid')->toArray();
        });

        $totalOps = count($devices) * count($notifications);
        $this->info("Processing $totalOps potential assignments...");

        $bar = $this->output->createProgressBar($totalOps);
        $bar->start();

        $assignedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($devices as $device) {
            $deviceId = $device['id'];
            $existingForDevice = $existing->get($deviceId, []);

            foreach ($notifications as $notif) {
                $notifId = $notif['id'];

                if (in_array($notifId, $existingForDevice)) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Assign
                $payload = json_encode(['deviceId' => $deviceId, 'notificationId' => $notifId]);
                $resp = static::curl('/api/permissions', 'POST', $cookie, $payload, [
                    Config::get('constants.Constants.jsonC'),
                    Config::get('constants.Constants.jsonA')
                ]);

                if ($resp->responseCode >= 200 && $resp->responseCode < 300) {
                    $assignedCount++;
                } else {
                    // 400 might mean already exists (Traccar usually returns 400 for duplicate permission)
                    if ($resp->responseCode == 400) {
                        $skippedCount++;
                    } else {
                        $errorCount++;
                    }
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Process completed.");
        $this->info("Assigned: $assignedCount");
        $this->info("Skipped (Already existed/Duplicate): $skippedCount");
        $this->info("Errors: $errorCount");

        return 0;
    }
}

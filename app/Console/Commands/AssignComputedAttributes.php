<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;

class AssignComputedAttributes extends Command
{
    use Curl;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assign:computed-attributes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign all computed attributes to all devices via Traccar API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Starting process to assign computed attributes...");

        // 1. Get Session (Try DB first, then API)
        $cookie = null;
        $adminUser = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)
            ->whereNotNull('traccarSession')
            ->first();

        if ($adminUser) {
            $this->info("Using stored session from admin user: " . $adminUser->email);
            $cookie = $adminUser->traccarSession;
        }

        // Test the cookie if we have one, or fetch new if we don't
        $needLogin = true;
        if ($cookie) {
             // Quick test: fetch devices (lightweight)
             $testResp = static::curl('/api/devices?limit=1', 'GET', $cookie, '', [Config::get('constants.Constants.jsonA')]);
             if ($testResp->responseCode == 200) {
                 $needLogin = false;
                 $this->info("Stored session is valid.");
             } else {
                 $this->warn("Stored session expired (Code: {$testResp->responseCode}). Re-authenticating...");
             }
        }

        if ($needLogin) {
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

            if ($response->responseCode < 200 || $response->responseCode >= 300) {
                $this->error("Login failed. Code: " . $response->responseCode . " Error: " . $response->error);
                return 1;
            }

            // Optionally update the DB with the new session if we found an admin user
            if ($adminUser && $cookie) {
                $adminUser->update(['traccarSession' => $cookie]);
                $this->info("Updated admin user session in DB.");
            } elseif ($cookie) {
                // Try to find an admin to store it?
                $anyAdmin = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->first();
                if ($anyAdmin) {
                     $anyAdmin->update(['traccarSession' => $cookie]);
                     $this->info("Stored new session for admin user: " . $anyAdmin->email);
                }
            }

            $this->info("Login successful.");
        }

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

        // 3. Get Computed Attributes
        $this->info("Fetching computed attributes...");
        $respAttrs = static::curl('/api/attributes/computed', 'GET', $cookie, '', [Config::get('constants.Constants.jsonA')]);

        if ($respAttrs->responseCode != 200) {
            $this->error("Failed to fetch attributes. Code: " . $respAttrs->responseCode);
            return 1;
        }

        $attrs = json_decode($respAttrs->response, true);
        if (!is_array($attrs)) {
            $this->error("Failed to parse attributes response.");
            return 1;
        }
        $this->info("Found " . count($attrs) . " computed attributes.");

        // 4. Get Existing Permissions (Optimization)
        $this->info("Fetching existing permissions from local DB...");
        // Fetch all rows from tc_device_attribute
        // We use DB facade directly since there might not be a dedicated model
        $existingRows = \Illuminate\Support\Facades\DB::table('tc_device_attribute')
            ->select('deviceid', 'attributeid')
            ->get();

        $existing = [];
        foreach ($existingRows as $row) {
            $existing[$row->deviceid][] = $row->attributeid;
        }
        $this->info("Found existing permissions for " . count($existing) . " devices.");

        // 5. Assign
        $totalOps = count($devices) * count($attrs);
        $this->info("Processing $totalOps potential assignments...");

        $bar = $this->output->createProgressBar($totalOps);
        $bar->start();

        $assignedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($devices as $device) {
            $deviceId = $device['id'];
            $existingForDevice = $existing[$deviceId] ?? [];

            foreach ($attrs as $attr) {
                $attrId = $attr['id'];

                // Check if already exists locally
                if (in_array($attrId, $existingForDevice)) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Blind POST - Check logic handled by API returning 400 for duplicates
                $payload = json_encode(['deviceId' => $deviceId, 'attributeId' => $attrId]);
                $resp = static::curl('/api/permissions', 'POST', $cookie, $payload, [
                    Config::get('constants.Constants.jsonC'),
                    Config::get('constants.Constants.jsonA')
                ]);

                if ($resp->responseCode >= 200 && $resp->responseCode < 300) {
                    $assignedCount++;
                    // Optionally update local cache if we want to be super strict in one run
                    $existing[$deviceId][] = $attrId;
                } else {
                    // 400 means Bad Request, usually "duplicate key" or "already exists" in Traccar permissions
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
        $this->info("Skipped (Already existed): $skippedCount");
        $this->info("Errors: $errorCount");

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;
use App\Models\User;

class AddTrackerModelComputedAttributes extends Command
{
    use Curl;

    protected $signature = 'traccar:add-tracker-model-attributes {--dry-run}';

    protected $description = 'Add tracker model based computed attributes for all vehicle models.';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Resolving Traccar session from admin user or config...');

        $cookie = null;
        $adminUser = User::where('email', 'admin@gmail.com')
            ->whereNotNull('traccarSession')
            ->first();

        if ($adminUser) {
            $this->info('Using stored session from admin user: ' . $adminUser->email);
            $cookie = $adminUser->traccarSession;
        }

        $needLogin = false;
        if ($cookie) {
            $testResp = static::curl('/api/devices?limit=1', 'GET', $cookie, '', [Config::get('constants.Constants.jsonA')]);
            if ($testResp->responseCode == 200) {
                $needLogin = false;
                $this->info('Stored session is valid.');
            } else {
                $this->warn('Stored session expired (Code: ' . $testResp->responseCode . '). Re-authenticating...');
            }
        }

        if ($needLogin) {
            $email = Config::get('constants.Constants.adminEmail');
            $password = Config::get('constants.Constants.adminPassword');
            $host = Config::get('constants.Constants.host');

            if (!$email || !$password || !$host) {
                $this->error('Missing Traccar admin configuration in constants.Constants');
                return 1;
            }

            $this->info('Logging in to Traccar API at ' . $host . ' as ' . $email . '...');

            $data = 'email=' . urlencode($email) . '&password=' . urlencode($password);
            $resp = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);

            $cookie = $resp->cookieData ?? session('cookie');

            if ($resp->responseCode < 200 || $resp->responseCode >= 300 || !$cookie) {
                $this->error('Failed to login to Traccar. Code: ' . ($resp->responseCode ?? 0));
                return 1;
            }

            if ($adminUser && $cookie) {
                $adminUser->update(['traccarSession' => $cookie]);
                $this->info('Updated admin user session in DB.');
            } elseif ($cookie) {
                $anyAdmin = User::where('role', User::ROLE_ADMIN)->first();
                if ($anyAdmin) {
                    $anyAdmin->update(['traccarSession' => $cookie]);
                    $this->info('Stored new session for admin user: ' . $anyAdmin->email);
                }
            }
        }

        $this->info('Fetching existing computed attributes...');

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $respAttrs = static::curl('/api/attributes/computed', 'GET', $cookie, '', $headers);

        if ($respAttrs->responseCode < 200 || $respAttrs->responseCode >= 300) {
            $this->error('Failed to fetch computed attributes. Code: ' . ($respAttrs->responseCode ?? 0));
            return 1;
        }

        $existing = json_decode($respAttrs->response ?? '[]', true) ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $existingByDescription = [];
        foreach ($existing as $ex) {
            $desc = isset($ex['description']) ? (string) $ex['description'] : '';
            if ($desc !== '') {
                $existingByDescription[$desc] = true;
            }
        }

        $trackerModels = [
            'Teltonika-FMC-003',
            'Teltonika-FMC-150',
            'Teltonika-FMC-130',
            'Teltonika-FMC-920',
        ];

        if (empty($trackerModels)) {
            $this->info('No tracker models found in VehicleModel list. Nothing to do.');
            return 0;
        }

        $this->info('Tracker models: ' . implode(', ', $trackerModels));

        $created = 0;
        $skipped = 0;

        $this->info('Processing ' . count($existing) . ' existing computed attributes for ' . count($trackerModels) . ' tracker models...');

        foreach ($existing as $ex) {
            $baseDescription = isset($ex['description']) ? trim((string) $ex['description']) : '';
            $baseAttribute = isset($ex['attribute']) ? trim((string) $ex['attribute']) : '';
            $expression = isset($ex['expression']) ? (string) $ex['expression'] : '';
            $type = isset($ex['type']) ? (string) $ex['type'] : 'number';

            if ($baseDescription === '' || $baseAttribute === '' || $expression === '') {
                continue;
            }

            foreach ($trackerModels as $tracker) {
                $tracker = trim((string) $tracker);
                if ($tracker === '') {
                    continue;
                }
                $trackerSlug = preg_replace('/[^A-Za-z0-9_]+/', '_', $tracker);

                $descriptionTracker = $baseDescription . ' [' . $tracker . ']';
                $attributeTracker = $baseAttribute;

                if (isset($existingByDescription[$descriptionTracker])) {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'description' => $descriptionTracker,
                    'attribute' => $attributeTracker,
                    'expression' => $expression,
                    'type' => $type,
                ];

                if ($dryRun) {
                    $this->line('Would create: ' . $descriptionTracker . ' attribute=' . $attributeTracker);
                    $created++;
                    continue;
                }

                $respCreate = static::curl('/api/attributes/computed', 'POST', $cookie, json_encode($payload), $headers);
                if ($respCreate->responseCode >= 200 && $respCreate->responseCode < 300) {
                    $created++;
                    $existingByDescription[$descriptionTracker] = true;
                } else {
                    $this->warn('Failed to create ' . $descriptionTracker . ' (code ' . ($respCreate->responseCode ?? 0) . ')');
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run completed. Would create ' . $created . ' attributes; skipped ' . $skipped . '.');
        } else {
            $this->info('Done. Created ' . $created . ' attributes; skipped existing ' . $skipped . '.');
        }

        return 0;
    }
}

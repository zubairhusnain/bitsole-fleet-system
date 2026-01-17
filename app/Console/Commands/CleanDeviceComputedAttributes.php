<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\Curl;
use Illuminate\Support\Facades\Config;

class CleanDeviceComputedAttributes extends Command
{
    use Curl;

    protected $signature = 'traccar:clean-device-computed {device_id}';

    protected $description = 'Keep device saved computed attributes and remove all other computed attributes for a given device id';

    public function handle()
    {
        $deviceId = (int)$this->argument('device_id');
        if ($deviceId <= 0) {
            $this->error('Invalid device_id');
            return 1;
        }

        try {
            $email = Config::get('constants.Constants.adminEmail');
            $password = Config::get('constants.Constants.adminPassword');
            if (!$email || !$password) {
                $this->error('Missing Traccar admin configuration in constants.Constants');
                return 1;
            }

            $dataLogin = 'email=' . urlencode($email) . '&password=' . urlencode($password);
            $respLogin = static::curl('/api/session', 'POST', '', $dataLogin, [Config::get('constants.Constants.urlEncoded')]);
            $cookie = $respLogin->cookieData ?? session('cookie');
            if ($respLogin->responseCode < 200 || $respLogin->responseCode >= 300 || !$cookie) {
                $this->error('Failed to login to Traccar. Code: ' . ($respLogin->responseCode ?? 0));
                return 1;
            }

            $headers = ['Content-Type: application/json', 'Accept' => 'application/json'];

            $respDevice = static::curl('/api/devices/' . $deviceId, 'GET', $cookie, '', $headers);
            if ($respDevice->responseCode < 200 || $respDevice->responseCode >= 300) {
                $this->error('Failed to fetch device ' . $deviceId . '. Code: ' . ($respDevice->responseCode ?? 0));
                return 1;
            }

            $body = json_decode($respDevice->response ?? '{}', true) ?? [];
            $attrs = [];
            if (isset($body['attributes'])) {
                if (is_array($body['attributes'])) {
                    $attrs = $body['attributes'];
                } elseif (is_string($body['attributes'])) {
                    $decoded = json_decode($body['attributes'], true);
                    $attrs = is_array($decoded) ? $decoded : [];
                }
            }

            $keep = [];
            if (isset($attrs['odometerAttr']) && trim((string)$attrs['odometerAttr']) !== '') {
                $keep[] = trim((string)$attrs['odometerAttr']);
            }
            if (isset($attrs['fuelAttr']) && trim((string)$attrs['fuelAttr']) !== '') {
                $keep[] = trim((string)$attrs['fuelAttr']);
            }

            $keepMap = [];
            foreach ($keep as $name) {
                $n = trim((string)$name);
                if ($n === '') {
                    continue;
                }
                $keepMap[mb_strtolower($n)] = true;
            }

            if (empty($keepMap)) {
                $this->info('No saved attributes found for device ' . $deviceId . '. Nothing to clean.');
                return 0;
            }

            $respAttrs = static::curl('/api/attributes/computed', 'GET', $cookie, '', $headers);
            if ($respAttrs->responseCode < 200 || $respAttrs->responseCode >= 300) {
                $this->error('Failed to fetch computed attributes. Code: ' . ($respAttrs->responseCode ?? 0));
                return 1;
            }

            $computed = json_decode($respAttrs->response ?? '[]', true) ?? [];
            if (!is_array($computed)) {
                $computed = [];
            }

            $deleteAttrIds = [];
            foreach ($computed as $a) {
                $attrName = isset($a['attribute']) ? trim((string)$a['attribute']) : '';
                if ($attrName === '') {
                    continue;
                }
                $key = mb_strtolower($attrName);
                if (isset($keepMap[$key])) {
                    $this->info('Skip attribute ' . $keepMap[$key] . '. Nothing to clean.');
                    continue;
                }
                if (!isset($a['id'])) {
                    continue;
                }
                $deleteAttrIds[] = (int)$a['id'];
            }
            $this->info('removing attributes ' . count($deleteAttrIds) );
            $removed = 0;
            foreach ($deleteAttrIds as $attrId) {
                $payload = json_encode(['deviceId' => $deviceId, 'attributeId' => $attrId]);
                $respDelete = static::curl('/api/permissions', 'DELETE', $cookie, $payload, $headers);
                if ($respDelete->responseCode >= 200 && $respDelete->responseCode < 300) {
                    $removed++;
                }
            }

            $this->info('Removed ' . $removed . ' computed attributes from device ' . $deviceId);
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error cleaning computed attributes: ' . $e->getMessage());
            return 1;
        }
    }
}

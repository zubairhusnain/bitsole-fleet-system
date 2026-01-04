<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;

class AssetActivityLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_activity_passes_limit_param_to_traccar_api()
    {
        $limit = 123; // Unique limit to test
        
        Http::fake(function (Request $request) use ($limit) {
            // Check if the request is for the Traccar reports API
            if (strpos($request->url(), '/api/reports/') !== false) {
                // Assert that the limit parameter is present in the query string
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);
                if (isset($queryParams['limit']) && $queryParams['limit'] == $limit) {
                     return Http::response([], 200);
                }
            }
            return Http::response([], 200);
        });

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Create a device
        $device = new \App\Models\Devices();
        $device->device_id = 999;
        $device->distributor_id = $user->id;
        $device->save();
        $user->role = 3; // Admin
        $user->save();

        $this->get("/web/reports/asset-activity?from_date=2024-05-16&to_date=2024-05-16&limit={$limit}");

        // Verify that at least one request was made with the correct limit
        Http::assertSent(function (Request $request) use ($limit) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);
            return isset($queryParams['limit']) && $queryParams['limit'] == $limit;
        });
    }

    public function test_asset_activity_uses_default_limit_100_if_not_provided()
    {
        $defaultLimit = 100;
        
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $device = new \App\Models\Devices();
        $device->device_id = 999;
        $device->distributor_id = $user->id;
        $device->save();
        $user->role = 3;
        $user->save();

        $this->get("/web/reports/asset-activity?from_date=2024-05-16&to_date=2024-05-16");

        Http::assertSent(function (Request $request) use ($defaultLimit) {
            if (strpos($request->url(), '/api/reports/') !== false) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);
                return isset($queryParams['limit']) && $queryParams['limit'] == $defaultLimit;
            }
            return false;
        });
    }
}

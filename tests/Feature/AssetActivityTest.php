<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_activity_endpoint_returns_correct_structure()
    {
        Http::fake([
            '*/api/reports/route*' => Http::response([
                [
                    'deviceId' => 1,
                    'fixTime' => '2024-05-16T10:00:00Z',
                    'latitude' => 12.34,
                    'longitude' => 56.78,
                    'address' => 'Test Address'
                ]
            ], 200),
            '*/api/reports/events*' => Http::response([
                [
                    'deviceId' => 1,
                    'type' => 'ignitionOn',
                    'eventTime' => '2024-05-16T10:05:00Z',
                    'attributes' => []
                ]
            ], 200),
            '*/api/reports/summary*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Test Vehicle',
                    'distance' => 100,
                    'averageSpeed' => 50
                ]
            ], 200),
        ]);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Test with specific device
        $response = $this->get('/web/reports/asset-activity?from_date=2024-05-16T00:00&to_date=2024-05-16T23:59&device_ids[]=1');
        $response->assertStatus(200);
        $response->assertJsonStructure(['header', 'rows']);
    }

    public function test_asset_activity_with_all_devices()
    {
        Http::fake([
            '*/api/reports/route*' => Http::response([], 200),
            '*/api/reports/events*' => Http::response([], 200),
            '*/api/reports/summary*' => Http::response([], 200),
        ]);

        $user = \App\Models\User::factory()->create();

        // Create a device manually since factory might be missing
         $device = new \App\Models\Devices();
         $device->device_id = 999;
         $device->distributor_id = $user->id; // Make it accessible if user is distributor, or link it
         $device->save();

        $user->role = 3; // Admin
         $user->save();

        $this->actingAs($user);

        $response = $this->get('/web/reports/asset-activity?from_date=2024-05-16T00:00&to_date=2024-05-16T23:59');

        $response->assertStatus(200);
    }

    public function test_asset_activity_date_range_limit()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Request 32 days
        $from = '2024-01-01';
        $to = '2024-02-02'; // 32 days

        $response = $this->get("/web/reports/asset-activity?from_date=$from&to_date=$to");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Date range cannot exceed 31 days.']);
    }
}

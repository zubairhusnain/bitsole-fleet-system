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

        // If user is normal user, we need to attach
        // But factory user role?
        // Let's make user Admin to simplify accessibility check?
        // Or assume factory user is default role.

        // If scopeAccessibleByUser logic:
        // if role is ADMIN (1), returns all.

        $user->role = 3; // Admin
         $user->save();

        $this->actingAs($user);

        // Test without device_ids (All Vehicles)
        // We need to ensure the user has access to at least one device, otherwise it returns empty 200.

        $response = $this->get('/web/reports/asset-activity?from_date=2024-05-16T00:00&to_date=2024-05-16T23:59');

        // If this fails with 500, we found the issue.
        $response->assertStatus(200);
    }
}

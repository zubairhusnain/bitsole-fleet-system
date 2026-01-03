<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetActivityTest extends TestCase
{
    // use RefreshDatabase; // Use if we need to migrate DB, but we mock Http so maybe not needed if we mock User or use existing.

    public function test_asset_activity_endpoint_returns_correct_structure()
    {
        // Mock Traccar API responses
        Http::fake([
            '*/api/reports/route*' => Http::response([
                [
                    'fixTime' => '2024-05-16T10:00:00Z',
                    'latitude' => 25.0,
                    'longitude' => 55.0,
                    'speed' => 10.0, // knots
                    'attributes' => ['ignition' => true, 'fuel' => 50.5]
                ]
            ], 200),
            '*/api/reports/events*' => Http::response([
                [
                    'eventTime' => '2024-05-16T10:05:00Z',
                    'type' => 'geofenceEnter',
                    'attributes' => [],
                    'deviceName' => 'Test Vehicle'
                ]
            ], 200),
            '*/api/reports/summary*' => Http::response([
                [
                    'deviceName' => 'Test Vehicle',
                    'distance' => 1000
                ]
            ], 200),
        ]);

        // Mock User
        $user = User::factory()->make(['id' => 1]); // use make to avoid DB hit if possible, or create if needed
        // Assuming Auth middleware checks DB, we might need 'create'.
        // Let's try 'actingAs' with a mock user.
        
        $response = $this->actingAs($user)
                         ->get('/web/reports/asset-activity?from_date=2024-05-16T00:00&to_date=2024-05-16T23:59&device_ids[]=1');

        $response->assertStatus(200);
        
        $json = $response->json();
        
        // Assert Header
        $this->assertEquals('Test Vehicle', $json['header']['vehicleId']);
        
        // Assert Rows
        $this->assertCount(2, $json['rows']);
        
        // Check Row 1 (Position)
        $row1 = $json['rows'][0]; // Earlier time
        $this->assertEquals('Position Log', $row1['status']);
        $this->assertEquals('position', $row1['rawType']);
        
        // Check Row 2 (Event)
        $row2 = $json['rows'][1];
        $this->assertEquals('Entered geofence', $row2['status']); // Friendly name check
        $this->assertEquals('geofenceEnter', $row2['rawType']);
    }
}

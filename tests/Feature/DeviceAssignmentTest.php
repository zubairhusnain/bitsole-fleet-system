<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Devices;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class DeviceAssignmentTest extends TestCase
{
    // Disable CSRF protection for this test
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_fleet_manager_can_assign_one_device_to_multiple_viewers()
    {
        // 1. Create Fleet Manager
        $manager = User::create([
            'name' => 'Test Manager ' . time(),
            'email' => 'manager_'.time().rand(100,999).'@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_FLEET_MANAGER,
        ]);

        // 2. Create Device owned by Manager
        // Need to ensure unique device_id
        $traccarId = 90000000 + rand(0, 99999);
        $device = new Devices();
        $device->manager_id = $manager->id;
        $device->device_id = $traccarId;
        $device->save();

        // 3. Create Two Fleet Viewers managed by this Manager
        $viewer1 = User::create([
            'name' => 'Viewer 1',
            'email' => 'viewer1_'.time().rand(100,999).'@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_USER,
            'manager_id' => $manager->id,
        ]);

        $viewer2 = User::create([
            'name' => 'Viewer 2',
            'email' => 'viewer2_'.time().rand(100,999).'@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_USER,
            'manager_id' => $manager->id,
        ]);

        try {
            // 4. Assign Device to Viewer 1
            $response1 = $this->actingAs($manager)->putJson("/web/users/{$viewer1->id}", [
                'device_ids' => [$traccarId], // Traccar ID
            ]);
            $response1->assertStatus(200);

            // 5. Assign SAME Device to Viewer 2
            $response2 = $this->actingAs($manager)->putJson("/web/users/{$viewer2->id}", [
                'device_ids' => [$traccarId], // Traccar ID
            ]);
            $response2->assertStatus(200);

            // 6. Verify Device is assigned to BOTH
            $this->assertTrue($viewer1->devices()->where('devices.id', $device->id)->exists(), 'Viewer 1 should have device assigned');
            $this->assertTrue($viewer2->devices()->where('devices.id', $device->id)->exists(), 'Viewer 2 should have device assigned');

            // 7. Verify API response for Show User
            $showResp1 = $this->actingAs($manager)->getJson("/web/users/{$viewer1->id}");
            $showResp1->assertJsonFragment(['assigned_device_ids' => [$traccarId]]);

            $showResp2 = $this->actingAs($manager)->getJson("/web/users/{$viewer2->id}");
            $showResp2->assertJsonFragment(['assigned_device_ids' => [$traccarId]]);

        } finally {
            // Cleanup
            $viewer1->devices()->detach();
            $viewer2->devices()->detach();
            $device->forceDelete(); // Hard delete to remove
            $viewer1->forceDelete();
            $viewer2->forceDelete();
            $manager->forceDelete();
        }
    }
}

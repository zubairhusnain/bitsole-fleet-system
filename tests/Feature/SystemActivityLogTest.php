<?php

namespace Tests\Feature;

use App\Models\SystemActivityLog;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_logging_user_creation_update_and_deletion()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        // 1. CREATE
        $userData = [
            'name' => 'Test User ' . uniqid(),
            'email' => 'test' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_USER,
        ];

        $response = $this->postJson('/web/users', $userData);
        $response->assertStatus(201);

        $createdUser = User::where('email', $userData['email'])->first();
        $this->assertNotNull($createdUser);

        // Verify Log
        $log = SystemActivityLog::where('action', 'CREATE')
            ->where('module', 'User')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($userData['email'], $log->new_data['email']);
        $this->assertEquals($admin->id, $log->user_id);

        // 2. UPDATE
        $updateData = ['name' => 'Updated Name', 'email' => $userData['email'], 'role' => User::ROLE_USER];
        $response = $this->putJson("/web/users/{$createdUser->id}", $updateData);
        $response->assertStatus(200);

        // Verify Log
        $log = SystemActivityLog::where('action', 'UPDATE')
            ->where('module', 'User')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Updated Name', $log->new_data['name']);
        $this->assertEquals($userData['name'], $log->old_data['name']);

        // 3. DELETE
        $response = $this->deleteJson("/web/users/{$createdUser->id}");
        $response->assertStatus(200);

        // Verify Log
        $log = SystemActivityLog::where('action', 'DELETE')
            ->where('module', 'User')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($createdUser->id, $log->old_data['id']);

        // Cleanup
        $admin->delete();
    }

    public function test_logging_vehicle_model_operations()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        // 1. CREATE
        $modelData = [
            'modelname' => 'Test Model ' . uniqid(),
            'attributes' => ['odometer' => []]
        ];

        $response = $this->postJson('/web/settings/vehicle-models', $modelData);
        $response->assertStatus(200); // Controller returns 200

        $createdModel = VehicleModel::where('modelname', $modelData['modelname'])->first();
        $this->assertNotNull($createdModel);

        $log = SystemActivityLog::where('action', 'CREATE')
            ->where('module', 'VehicleModel')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($modelData['modelname'], $log->new_data['modelname']);

        // 2. UPDATE
        $updateData = [
            'modelname' => $modelData['modelname'],
            'attributes' => ['odometer' => [['name' => 'Test', 'key' => 'test']]]
        ];
        $response = $this->putJson("/web/settings/vehicle-models/{$createdModel->id}", $updateData);
        $response->assertStatus(200);

        $log = SystemActivityLog::where('action', 'UPDATE')
            ->where('module', 'VehicleModel')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($updateData['attributes'], $log->new_data['attributes']);

        // 3. DELETE
        $response = $this->deleteJson("/web/settings/vehicle-models/{$createdModel->id}");
        $response->assertStatus(200);

        $log = SystemActivityLog::where('action', 'DELETE')
            ->where('module', 'VehicleModel')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($createdModel->id, $log->old_data['id']);

        // Cleanup
        $admin->delete();
    }

    public function test_fetching_logs_via_api()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        // Create some logs
        SystemActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => 'CREATE',
            'module' => 'TestModule',
            'request_path' => 'test/path',
            'description' => 'Test description',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/web/system-logs?module=TestModule');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.module', 'TestModule');
    }

    public function test_unauthorized_access_to_logs()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user);

        $response = $this->getJson('/web/system-logs');
        $response->assertStatus(403);
    }
}

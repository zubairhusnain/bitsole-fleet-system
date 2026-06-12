<?php

namespace Tests\Feature;

use App\Http\Controllers\DriverController;
use App\Http\Controllers\LiveTrackingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VehicleController;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function fleetViewer(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_USER,
            'password' => 'password',
        ], $attrs));
    }

    private function grant(User $user, string $module, bool $read = true, bool $create = false, bool $update = false, bool $delete = false): void
    {
        UserPermission::create([
            'user_id' => $user->id,
            'module_key' => $module,
            'can_access' => $read,
            'can_read' => $read,
            'can_create' => $create,
            'can_update' => $update,
            'can_delete' => $delete,
        ]);
    }

    public function test_mobile_routes_require_authentication(): void
    {
        $this->getJson('/api/mobile/auth/me')->assertUnauthorized();
        $this->getJson('/api/mobile/live/positions')->assertUnauthorized();
        $this->getJson('/api/mobile/vehicles')->assertUnauthorized();
        $this->getJson('/api/mobile/drivers')->assertUnauthorized();
        $this->getJson('/api/mobile/notifications/events')->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/mobile/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/mobile/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_me_returns_user_and_permissions(): void
    {
        $user = $this->fleetViewer();
        $this->grant($user, 'vehicles');
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['user', 'permissions']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->fleetViewer();
        $accessToken = $user->createToken('mobile');
        $plain = $accessToken->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->postJson('/api/mobile/auth/logout')
            ->assertOk()
            ->assertJson(['status' => 'logged_out']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);
    }

    public function test_profile_update_stores_fcm_token(): void
    {
        $user = $this->fleetViewer();
        Sanctum::actingAs($user);

        $this->putJson('/api/mobile/auth/profile', [
            'fcm_token' => 'test-fcm-token-123',
        ])
            ->assertOk()
            ->assertJsonPath('user.fcm_token', 'test-fcm-token-123');
    }

    public function test_fleet_viewer_without_vehicle_permission_gets_forbidden(): void
    {
        Sanctum::actingAs($this->fleetViewer());

        $this->getJson('/api/mobile/vehicles')->assertForbidden();
        $this->getJson('/api/mobile/live/positions')->assertForbidden();
    }

    public function test_fleet_viewer_with_permissions_can_access_fleet_endpoints(): void
    {
        $user = $this->fleetViewer();
        $this->grant($user, 'vehicles');
        $this->grant($user, 'drivers');
        Sanctum::actingAs($user);

        $this->mock(LiveTrackingController::class, function ($mock) {
            $mock->shouldReceive('current')->once()->andReturn(response()->json([
                'positions' => [['id' => 1, 'name' => 'Truck 1', 'latitude' => 29.96, 'longitude' => -98.22]],
            ]));
        });
        $this->mock(VehicleController::class, function ($mock) {
            $mock->shouldReceive('index')->once()->andReturn(response()->json(['data' => []]));
            $mock->shouldReceive('detail')->once()->andReturn(response()->json(['detail' => ['device' => ['id' => 1]]]));
            $mock->shouldReceive('show')->once()->andReturn(response()->json(['device_id' => 1]));
            $mock->shouldReceive('trips')->once()->andReturn(response()->json(['trips' => [], 'total' => 0]));
            $mock->shouldReceive('driver')->once()->andReturn(response()->json(['driver' => null]));
            $mock->shouldReceive('performance')->once()->andReturn(response()->json(['performance' => []]));
            $mock->shouldReceive('rating')->once()->andReturn(response()->json(['rating' => null]));
        });
        $this->mock(DriverController::class, function ($mock) {
            $mock->shouldReceive('index')->once()->andReturn(response()->json(['drivers' => []]));
        });
        $this->mock(NotificationController::class, function ($mock) {
            $mock->shouldReceive('events')->once()->andReturn(response()->json([]));
            $mock->shouldReceive('unreadCount')->once()->andReturn(response()->json(['count' => 0]));
            $mock->shouldReceive('markAllRead')->once()->andReturn(response()->json(['status' => 'ok']));
        });

        $this->getJson('/api/mobile/live/positions')
            ->assertOk()
            ->assertJsonStructure(['positions']);

        $this->getJson('/api/mobile/vehicles')->assertOk();
        $this->getJson('/api/mobile/vehicles/1/detail')->assertOk();
        $this->getJson('/api/mobile/vehicles/1')->assertOk();
        $this->getJson('/api/mobile/vehicles/1/trips')->assertOk();
        $this->getJson('/api/mobile/vehicles/1/driver')->assertOk();
        $this->getJson('/api/mobile/vehicles/1/performance')->assertOk();
        $this->getJson('/api/mobile/vehicles/1/rating')->assertOk();
        $this->getJson('/api/mobile/drivers')->assertOk()->assertJsonStructure(['drivers']);
        $this->getJson('/api/mobile/notifications/events')->assertOk();
        $this->getJson('/api/mobile/notifications/unread-count')->assertOk()->assertJson(['count' => 0]);
        $this->postJson('/api/mobile/notifications/mark-read')->assertOk();
    }

    public function test_admin_is_forbidden_on_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/mobile/notifications/events')->assertForbidden();
    }

    public function test_admin_can_access_fleet_routes_but_not_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->mock(VehicleController::class, function ($mock) {
            $mock->shouldReceive('index')->once()->andReturn(response()->json(['data' => []]));
        });
        $this->mock(DriverController::class, function ($mock) {
            $mock->shouldReceive('index')->once()->andReturn(response()->json(['drivers' => []]));
        });

        $this->getJson('/api/mobile/vehicles')->assertOk();
        $this->getJson('/api/mobile/drivers')->assertOk();
        $this->getJson('/api/mobile/notifications/events')->assertForbidden();
    }
}

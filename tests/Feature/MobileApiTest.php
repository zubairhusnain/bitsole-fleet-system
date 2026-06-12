<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPermission;
use App\Services\DeviceService;
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

    private function mockLivePositions(array $positions = []): void
    {
        $this->mock(DeviceService::class, function ($mock) use ($positions) {
            $mock->shouldReceive('getLiveDevices')->andReturn($positions);
        });
    }

    public function test_mobile_routes_require_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/live/positions/current')->assertUnauthorized();
        $this->getJson('/api/vehicles')->assertUnauthorized();
        $this->getJson('/api/drivers')->assertUnauthorized();
        $this->getJson('/api/notifications/events')->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_me_returns_user_and_permissions(): void
    {
        $user = $this->fleetViewer();
        $this->grant($user, 'vehicles');
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
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
            ->postJson('/api/auth/logout')
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

        $this->putJson('/api/auth/profile', [
            'fcm_token' => 'test-fcm-token-123',
        ])
            ->assertOk()
            ->assertJsonPath('user.fcm_token', 'test-fcm-token-123');
    }

    public function test_fleet_viewer_without_vehicle_permission_gets_forbidden(): void
    {
        Sanctum::actingAs($this->fleetViewer());

        $this->getJson('/api/vehicles')->assertForbidden();

        $this->mockLivePositions();
        $this->getJson('/api/live/positions/current')
            ->assertOk()
            ->assertJsonPath('positions', []);
    }

    public function test_fleet_viewer_with_permissions_can_access_fleet_endpoints(): void
    {
        $user = $this->fleetViewer();
        $this->grant($user, 'vehicles');
        $this->grant($user, 'drivers');
        Sanctum::actingAs($user);

        $this->mockLivePositions([
            ['id' => 1, 'name' => 'Truck 1', 'latitude' => 29.96, 'longitude' => -98.22],
        ]);

        $this->getJson('/api/live/positions/current')
            ->assertOk()
            ->assertJsonStructure(['positions']);

        $this->getJson('/api/vehicles')->assertOk();
        $this->getJson('/api/drivers')->assertOk()->assertJsonStructure(['drivers']);
    }

    public function test_admin_is_forbidden_on_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/notifications/events')->assertForbidden();
    }

    public function test_admin_can_access_fleet_routes_but_not_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/vehicles')->assertOk();
        $this->getJson('/api/drivers')->assertOk();
        $this->getJson('/api/notifications/events')->assertForbidden();
    }
}

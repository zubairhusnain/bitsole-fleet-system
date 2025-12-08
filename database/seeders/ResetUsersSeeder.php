<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Devices;
use App\Http\Middleware\ModulePermission;

class ResetUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear Permissions
        // We truncate this table to reset IDs and remove all records.
        DB::statement('TRUNCATE TABLE user_permissions RESTART IDENTITY CASCADE');

        // 2. Delete Users (Soft Delete or Hard Delete?)
        // We use DB::table()->delete() to perform a hard delete without model events,
        // but triggering database foreign key constraints (ON DELETE SET NULL for devices).
        // We DO NOT use TRUNCATE on users because TRUNCATE CASCADE would wipe the devices table.
        DB::table('users')->delete();

        // 3. Reset User ID Sequence (PostgreSQL specific)
        // This ensures the new users get IDs 1, 2, 3.
        try {
            DB::statement('ALTER SEQUENCE users_id_seq RESTART WITH 1');
        } catch (\Exception $e) {
            // Sequence reset is optional; ignore if it fails (e.g. non-Postgres environment)
        }

        $password = Hash::make('87654321');

        // 4. Create Users
        // ID 1: Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'Superadmin@gmail.com',
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        // ID 2: Distributor
        $distributor = User::create([
            'name' => 'Distributor',
            'email' => 'distributor@gmail.com',
            'password' => $password,
            'role' => User::ROLE_DISTRIBUTOR,
            'email_verified_at' => now(),
        ]);

        // ID 3: Fleet Manager
        $fleetManager = User::create([
            'name' => 'Fleet Manager',
            'email' => 'admin@gmail.com',
            'manager_id'=>NULL,
            'distributor_id'=>$distributor->id,
            'password' => $password,
            'role' => User::ROLE_FLEET_MANAGER,
            'email_verified_at' => now(),
        ]);

        // 5. Assign Permissions to Fleet Manager
        $modules = array_keys(ModulePermission::modules());

        foreach ($modules as $moduleKey) {
            UserPermission::create([
                'user_id' => $fleetManager->id,
                'module_key' => $moduleKey,
                'can_access' => true,
                'can_read' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ]);
        }

        // 6. Update Existing Devices
        // Instead of creating new device rows, we simply update the ownership of ALL existing rows
        // in the devices table to the new Fleet Manager and Distributor.
        // We use withTrashed() to include any soft-deleted device records.
        $updatedCount = Devices::withTrashed()->update([
            'user_id' => $fleetManager->id,
            'distributor_id' => $distributor->id,
        ]);

        $this->command->info('Users reset, permissions assigned. ' . $updatedCount . ' existing devices updated to Fleet Manager & Distributor.');
    }
}

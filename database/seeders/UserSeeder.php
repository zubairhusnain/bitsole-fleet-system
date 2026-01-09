<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserPermission;
use App\Http\Middleware\ModulePermission;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@gmail.com', 'role' => User::ROLE_ADMIN],
            ['name' => 'Distributor User', 'email' => 'distributor@example.com', 'role' => User::ROLE_DISTRIBUTOR],
            ['name' => 'Manager User', 'email' => 'manager@example.com', 'role' => User::ROLE_FLEET_MANAGER],
            ['name' => 'Operator User', 'email' => 'operator@example.com', 'role' => User::ROLE_USER],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'role' => $data['role'],
                    'remember_token' => Str::random(10),
                ]
            );

            // Assign permissions for Admin and Distributor
            if ($user->role === User::ROLE_ADMIN || $user->role === User::ROLE_DISTRIBUTOR) {
                $modules = array_keys(ModulePermission::modules());
                foreach ($modules as $moduleKey) {
                    UserPermission::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'module_key' => $moduleKey,
                        ],
                        [
                            'can_access' => true,
                            'can_read' => true,
                            'can_create' => true,
                            'can_update' => true,
                            'can_delete' => true,
                        ]
                    );
                }
            }
        }
    }
}

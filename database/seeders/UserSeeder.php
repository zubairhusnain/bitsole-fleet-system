<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com'],
            ['name' => 'Manager User', 'email' => 'manager@example.com'],
            ['name' => 'Operator User', 'email' => 'operator@example.com'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'remember_token' => Str::random(10),
                ]
            );
        }
    }
}
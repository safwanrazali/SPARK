<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'AdminMpq'],
            [
                'name' => 'Admin',
                'email' => 'admin@spark.local',
                'password' => Hash::make('mPq@P+pKm!@!@'),
                'email_verified_at' => now(),
                'role' => User::ROLE_ADMINISTRATOR,
            ]
        );
    }
}

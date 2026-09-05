<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@daftari.local'],
            [
                'company_id' => null,
                'name' => 'Platform Admin',
                'password' => Hash::make('Admin@12345'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );
    }
}

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
            ['email' => 'admin@salescareplusmzg.com'],
            [
                'name' => 'Site Administrator',
                'password' => Hash::make('AdminSCP@2026'),
                'is_admin' => true,
            ]
        );
    }
}

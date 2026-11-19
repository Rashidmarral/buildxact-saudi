<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate, not updateOrCreate: this runs on every
        // `migrate --seed` (including a redeploy of an already-live
        // instance), and unconditionally overwriting the password/role
        // here would silently reset an operator's own changed credentials
        // back to whatever this seeder last set. Only the first run — a
        // genuinely fresh install with no admin yet — should touch
        // password/role at all.
        if (User::where('email', 'admin@daftari.local')->exists()) {
            return;
        }

        // A fixed, documented default password (the previous behavior)
        // is fine for the CodeCanyon installer wizard's own flow, which
        // never reaches this seeder — but `migrate --seed` run directly
        // against a live database is a real path an operator can take
        // without following that docs warning, and a public, guessable
        // password on a super_admin account left live is a real exposure.
        // A random one generated per-install closes that regardless of
        // whether the docs get read.
        $password = Str::password(16);

        User::create([
            'email' => 'admin@daftari.local',
            'company_id' => null,
            'name' => 'Platform Admin',
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->command?->warn("Created platform admin admin@daftari.local with password: {$password}");
        $this->command?->warn('Save this now — it is not stored anywhere else and will not be shown again. Change it after first login.');
    }
}

<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security audit finding CRIT-04: the seeded "Read-only auditor" admin
 * role stored a bare (full view+manage) grant for every permission key —
 * AdminRoleSeeder's firstOrCreate() means an already-installed deployment
 * keeps that original row forever, even after AdminPermissions::
 * systemRolePresets() was fixed to generate ":view"-suffixed (view-only)
 * keys for it. This migration updates any existing 'read_only' system
 * role in place so the fix actually takes effect for real installs, not
 * just fresh ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('admin_roles')->where('slug', 'read_only')->where('is_system', true)->first();

        if (! $role) {
            return;
        }

        DB::table('admin_roles')->where('id', $role->id)->update([
            'permissions' => json_encode(array_map(
                fn (string $key) => "{$key}:view",
                AdminPermissions::keys()
            )),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Deliberately irreversible — reverting would restore the
        // over-privileged grant this migration fixes.
    }
};

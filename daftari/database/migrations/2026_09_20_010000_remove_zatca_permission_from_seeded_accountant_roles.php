<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security audit finding M-08: Role::seedSystemRoles() uses firstOrCreate(),
 * so every company that had already seeded its system roles keeps the old
 * Accountant preset (including 'zatca') even after Permissions::
 * systemRolePresets() drops it. Backfill every already-seeded, still-system
 * Accountant role the same way CRIT-04 backfilled the read_only admin role.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'accountant')
            ->where('is_system', true)
            ->orderBy('id')
            ->each(function ($role) {
                $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

                if (in_array('zatca', $permissions, true)) {
                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values(array_diff($permissions, ['zatca']))),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Deliberately irreversible — we don't know which companies had
        // deliberately granted 'zatca' to their Accountant role themselves
        // versus inherited it only from the old default preset.
    }
};

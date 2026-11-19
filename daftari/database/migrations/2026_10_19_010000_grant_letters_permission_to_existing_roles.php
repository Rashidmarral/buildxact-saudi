<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "please create letter in my company letter pad ... other companies
 * can also upload for there documents" — Letters & Agreements existed
 * already, but was nested entirely inside the Machinery & Equipment
 * module/permission (routes/web.php), so a company without that paid
 * add-on had no access to it at all — not even the nav link showed.
 * Un-gating the routes (see routes/web.php) only fixes it going
 * forward for the account owner (who bypasses permission checks
 * entirely — see User::hasPermission()); a non-owner staff member only
 * gets in once their role actually carries the new 'letters' key.
 * Same backfill pattern as
 * 2026_09_20_010000_remove_zatca_permission_from_seeded_accountant_roles:
 * every already-seeded system Admin/Accountant/Sales role (firstOrCreate()
 * in Role::seedSystemRoles() means an existing row never picks up a
 * preset change on its own) gets 'letters' added if it doesn't already
 * have it, so every company's own team keeps up with the same access the
 * new company's seeded roles get automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->whereIn('slug', ['admin', 'accountant', 'sales'])
            ->where('is_system', true)
            ->orderBy('id')
            ->each(function ($role) {
                $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

                if (! in_array('letters', $permissions, true)) {
                    $permissions[] = 'letters';

                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values($permissions)),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Deliberately irreversible — we don't know which of these a
        // company then deliberately removed themselves after this ran.
    }
};

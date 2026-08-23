<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is enforced starting from this migration — every
     * account that already existed is grandfathered in as verified so
     * nobody already using the app gets locked out. Only accounts created
     * after this point (via the public registration form) actually go
     * through the verify-your-email flow.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally a no-op — there is no way to know which of these
        // rows were "really" verified afterward vs backfilled here, and
        // unverifying real accounts on a rollback would be destructive.
    }
};

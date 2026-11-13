<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security audit finding SEC-09: TOTP verification had no persisted
 * last-used time step, so a captured valid code could be replayed
 * (submitted again, or concurrently) and would keep validating until it
 * naturally expired (~90s with the one-step clock-drift window). This
 * column lets the login-time challenge reject a step at or below the one
 * already consumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_totp_step')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_totp_step');
        });
    }
};

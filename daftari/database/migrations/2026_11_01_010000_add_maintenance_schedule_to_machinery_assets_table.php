<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "build both those next" (maintenance scheduling + trip logs) —
 * mirrors the exact registration_expiry_date/registration_reminder_
 * sent_at pair already on this table (2026_09_29_090000), so
 * SendMachineryExpiryReminders' existing notify-once-then-clear-on-
 * renewal logic (remindForField()) covers this with one more call,
 * no new reminder machinery needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_assets', function (Blueprint $table) {
            $table->date('next_maintenance_due_date')->nullable()->after('insurance_reminder_sent_at');
            $table->timestamp('maintenance_reminder_sent_at')->nullable()->after('next_maintenance_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_assets', function (Blueprint $table) {
            $table->dropColumn(['next_maintenance_due_date', 'maintenance_reminder_sent_at']);
        });
    }
};

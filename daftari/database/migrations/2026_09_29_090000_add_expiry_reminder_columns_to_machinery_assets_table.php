<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_assets', function (Blueprint $table) {
            $table->timestamp('registration_reminder_sent_at')->nullable()->after('registration_expiry_date');
            $table->timestamp('insurance_reminder_sent_at')->nullable()->after('insurance_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_assets', function (Blueprint $table) {
            $table->dropColumn(['registration_reminder_sent_at', 'insurance_reminder_sent_at']);
        });
    }
};

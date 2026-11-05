<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedTinyInteger('last_reminder_tier')->nullable()->after('last_reminder_sent_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('invoice_dunning_enabled')->default(true)->after('invoice_approval_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('last_reminder_tier');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('invoice_dunning_enabled');
        });
    }
};

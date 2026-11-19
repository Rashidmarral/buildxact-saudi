<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add more... Auto-emailed monthly financial statements" — the last
 * item of Batch D. financial_statements_last_sent_period (a plain
 * 'YYYY-MM' string, not a real relation) is the "already sent this
 * period" guard — mirrors the *_reminder_sent_at pattern used
 * elsewhere (SendCompanyDocumentExpiryReminders), just period-keyed
 * instead of timestamp-keyed since this only ever fires once a month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('financial_statements_auto_email_enabled')->default(false)->after('cost_center_scope');
            $table->string('financial_statements_last_sent_period', 7)->nullable()->after('financial_statements_auto_email_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['financial_statements_auto_email_enabled', 'financial_statements_last_sent_period']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial-audit findings H3/M2/L1: three tables filtered/sorted on
 * columns that had no supporting index.
 *
 * - payments.status: the admin platform-wide Payments list filters by
 *   status across every company with no company_id predicate at all, so
 *   a plain company_id index doesn't help it.
 * - webhook_deliveries.created_at: each webhook's delivery log is shown
 *   newest-first; webhook_id was indexed but ordering within it wasn't.
 * - notifications.read_at: the notification bell's "unread" filter reads
 *   this column directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "can we make our debit note something like this" — a competitor
 * screenshot shared a structured "Reason type" dropdown (Price
 * correction / Additional charges or services / Freight billed after
 * the invoice / Other) alongside the free-text reason this app already
 * had. Nullable/optional: every debit note issued before this still
 * has a perfectly valid free-text reason with no category, and nothing
 * requires picking one going forward either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('reason_type', 30)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropColumn('reason_type');
        });
    }
};

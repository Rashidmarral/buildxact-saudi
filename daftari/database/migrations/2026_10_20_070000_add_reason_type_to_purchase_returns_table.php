<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "yes please do for purchase return as well" — the same structured
 * "Reason type" field just added to credit notes and debit notes.
 * Nullable/optional, same as both: no existing or new purchase return
 * is required to pick one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('reason_type', 30)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('reason_type');
        });
    }
};

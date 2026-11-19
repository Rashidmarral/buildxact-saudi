<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "can we make our credit note something like this" — the same
 * structured "Reason type" field just added to debit notes, with
 * categories suited to the opposite direction (crediting back, not
 * adding on top). Nullable/optional, same as debit notes: no existing
 * or new credit note is required to pick one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('reason_type', 30)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn('reason_type');
        });
    }
};

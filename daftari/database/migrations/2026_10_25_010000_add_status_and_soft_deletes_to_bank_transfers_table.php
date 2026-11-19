<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * There was previously no way to edit, void, or delete a bank transfer
 * once recorded — a wrong account, amount, or an entry that should have
 * been a different kind of transaction entirely had no safe correction
 * path. Mirrors the existing Payment/Receipt Voucher pattern: `status`
 * lets a mistaken transfer be voided (reversing its ledger entry and
 * excluding it from every balance/report query) rather than edited in
 * place once money has already moved, and soft deletes mean a delete
 * only hides the row — it can still be recovered if deleted by mistake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->string('status', 20)->default('issued')->after('notes');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropColumn(['status', 'deleted_at']);
        });
    }
};

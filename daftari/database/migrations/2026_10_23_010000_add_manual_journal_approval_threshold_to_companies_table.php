<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding GL-6: Expenses, Purchase Orders, Invoices, and
 * Quotations can all require sign-off above a threshold
 * (Company::po_approval_threshold et al.) — Manual Journal Entries, the
 * single most sensitive write in the whole system (a direct, unaudited
 * GL posting with no source document behind it), could not. Mirrors the
 * existing threshold pattern exactly: null = disabled, today's
 * instant-post behavior is unchanged for every company until an owner
 * opts in. See create_pending_manual_journal_entries_table for the
 * staging mechanism this threshold gates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('manual_journal_approval_threshold', 12, 2)->nullable()->after('quotation_approval_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('manual_journal_approval_threshold');
        });
    }
};

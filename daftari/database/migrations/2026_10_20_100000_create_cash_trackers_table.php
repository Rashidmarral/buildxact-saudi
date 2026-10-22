<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "something to tackle this kind of situation like i directly add two
 * deposit into other company account and add entries where this cash is
 * used and get report for it and that will not affect our accounting
 * system like a separate module if we miss anything live it there but
 * just to get exact report of that account to show where cash goes."
 *
 * A Cash Tracker is a free-standing, informal ledger for exactly that:
 * money that moved outside the company's formal books (an owner's direct
 * cash handout, an inter-company transfer managed by hand) but still
 * needs a clear "where did it go" report. Deliberately not a BankAccount
 * — it posts nothing to Account/JournalEntry, is never summed into
 * BankAccount::currentBalance(), never appears in the Custody Report,
 * trial balance, or any financial statement. It exists purely so an
 * operator can log deposits in and spends out with loose, non-validated
 * free text (who, what for) and get a simple running-balance report,
 * without that log being able to corrupt the real accounting records if
 * an entry is wrong or something is missed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('cash_tracker_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_tracker_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // deposit = cash coming into this tracked pool; withdrawal =
            // cash going back out of it (spent, handed to someone, or
            // returned) — a plain two-way ledger, not a chart of accounts.
            $table->string('type', 10);
            $table->decimal('amount', 12, 2);
            $table->string('party_name')->nullable();
            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'cash_tracker_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_tracker_entries');
        Schema::dropIfExists('cash_trackers');
    }
};

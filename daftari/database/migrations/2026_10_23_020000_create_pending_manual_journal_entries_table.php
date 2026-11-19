<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding GL-6's staging mechanism. A manual journal entry has no
 * pre-existing persistent record separate from the act of posting itself
 * — LedgerPostingService::postManual() creates the real JournalEntry +
 * lines in one atomic step, with its own balance check. Unlike Expense
 * (which already has its own row to sit in 'pending_approval' before
 * LedgerPostingService::postExpense() is ever called), there was nothing
 * to hold a manual entry's data between submission and approval without
 * either writing an unbalanced/unapproved row into JournalEntry itself
 * (risking every GL report needing a status filter retrofitted) or
 * building this: a separate staging table. Lines are stored as JSON
 * (mirroring the exact shape ManualJournalEntryController::store()
 * already validates: account_id/debit/credit/memo) since they have no
 * accounting effect at all until postManual() is actually called on
 * approval — only then do real JournalEntryLine rows exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_manual_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('description', 500);
            $table->json('lines');
            $table->decimal('total', 14, 2);
            $table->string('status', 20)->default('pending_approval'); // pending_approval, approved, rejected
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_manual_journal_entries');
    }
};

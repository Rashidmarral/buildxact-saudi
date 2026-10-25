<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding GL-01: duplicate-posting protection (LedgerPostingService
 * ::alreadyPosted()) was an unlocked SELECT before the posting
 * transaction opened — two concurrent callers for the same source event
 * could both pass it and each create a journal entry. Company-level
 * number locking (nextJournalNumber()) keeps entry_number unique under
 * concurrency, but never re-ran the source check inside that lock. A
 * real database-level uniqueness constraint is the only thing that
 * actually closes this race — LedgerPostingService::post() now catches a
 * violation of it and treats a losing concurrent writer as the same
 * "already posted" idempotent success alreadyPosted() already produces
 * for a sequential duplicate call.
 *
 * Safety note for the real production database: if this fails to apply
 * because duplicate (company_id, source_type, source_id) rows already
 * exist, DO NOT resolve that by deleting rows automatically — investigate
 * which of the duplicates is the real, correct posting and which is the
 * accidental double-post before removing anything from production
 * accounting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['company_id', 'source_type', 'source_id'], 'journal_entries_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_source_unique');
        });
    }
};

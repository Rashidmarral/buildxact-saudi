<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournalEntryLine extends Model
{
    protected $fillable = ['recurring_journal_entry_id', 'account_id', 'debit', 'credit', 'memo'];

    public function recurringJournalEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringJournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

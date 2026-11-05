<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * See the create_pending_manual_journal_entries_table migration for why
 * this exists as its own staging table rather than a status column on
 * JournalEntry itself.
 */
class PendingManualJournalEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'entry_date', 'description', 'lines', 'total', 'status',
        'journal_entry_id', 'created_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'lines' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RecurringJournalEntry extends Model
{
    use BelongsToCompany;

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'company_id', 'created_by', 'title', 'frequency',
        'start_date', 'next_run_date', 'end_date', 'status',
        'last_generated_at', 'generated_count',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'last_generated_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringJournalEntryLine::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The next run date after $from, per this recurrence's frequency —
     * kept as one method (used both to seed next_run_date on creation and
     * to advance it after each generation) so the two never drift apart,
     * mirroring RecurringInvoice::nextRunDateAfter().
     */
    public function nextRunDateAfter(\DateTimeInterface $from): Carbon
    {
        $date = Carbon::instance($from);

        return match ($this->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(),
        };
    }

    /**
     * Posts a real JournalEntry from this template's lines via
     * LedgerPostingService::postManual() (so it goes through the same
     * balance + period-lock validation as a hand-entered manual entry),
     * then advances this recurrence to its next run date — or marks it
     * completed once that would fall past end_date.
     */
    public function generateEntry(): JournalEntry
    {
        $lines = $this->lines->map(fn (RecurringJournalEntryLine $line) => [
            'account_id' => $line->account_id,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'memo' => $line->memo,
        ])->all();

        $entry = app(LedgerPostingService::class)->postManual(
            $this->company,
            $this->title,
            $this->next_run_date,
            $lines
        );

        $nextRun = $this->nextRunDateAfter($this->next_run_date);

        $this->update([
            'next_run_date' => $nextRun,
            'last_generated_at' => now(),
            'generated_count' => $this->generated_count + 1,
            'status' => $this->end_date && $nextRun->gt($this->end_date) ? 'completed' : $this->status,
        ]);

        return $entry;
    }
}

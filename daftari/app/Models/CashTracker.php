<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A standalone, informal cash ledger — see this feature's own migration
 * for the full "why." Intentionally has no relation to Account,
 * JournalEntry, or BankAccount; nothing here is posted or summed into
 * the real accounting records. No soft-deletes, by design — deleting a
 * tracker relies on the DB-level cascade to also remove its entries, and
 * this is meant to be a freely correctable scratch ledger, not a
 * second set of books with its own retention/audit trail.
 */
class CashTracker extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'currency', 'notes', 'created_by'];

    public function entries(): HasMany
    {
        return $this->hasMany(CashTrackerEntry::class)->orderBy('date')->orderBy('id');
    }

    public function balance(): float
    {
        return round(
            (float) $this->entries()->where('type', 'deposit')->sum('amount')
            - (float) $this->entries()->where('type', 'withdrawal')->sum('amount'),
            2
        );
    }

    public function totalDeposited(): float
    {
        return (float) $this->entries()->where('type', 'deposit')->sum('amount');
    }

    public function totalWithdrawn(): float
    {
        return (float) $this->entries()->where('type', 'withdrawal')->sum('amount');
    }
}

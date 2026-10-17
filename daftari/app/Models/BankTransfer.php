<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BankTransfer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'from_bank_account_id', 'to_bank_account_id', 'project_id', 'created_by',
        'amount', 'date', 'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Bank → cash is a withdrawal (cash physically taken out), cash →
     * bank is a deposit, anything else (bank→bank, cash→cash) is a plain
     * transfer — same classification ProjectCashFlowController's ledger
     * uses, exposed here so any other view/report can ask a transfer what
     * kind of movement it actually represents.
     */
    public function kind(): string
    {
        return match (true) {
            $this->fromAccount->type === 'bank' && $this->toAccount->type === 'cash' => 'withdrawal',
            $this->fromAccount->type === 'cash' && $this->toAccount->type === 'bank' => 'deposit',
            default => 'transfer',
        };
    }
}

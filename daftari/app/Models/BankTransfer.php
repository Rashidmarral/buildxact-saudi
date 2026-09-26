<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'from_bank_account_id', 'to_bank_account_id', 'created_by',
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
}

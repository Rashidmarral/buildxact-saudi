<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTrackerEntry extends Model
{
    use BelongsToCompany;

    public const TYPES = ['deposit', 'withdrawal'];

    protected $fillable = [
        'company_id', 'cash_tracker_id', 'date', 'type', 'amount',
        'party_name', 'purpose', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(CashTracker::class, 'cash_tracker_id');
    }

    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }
}

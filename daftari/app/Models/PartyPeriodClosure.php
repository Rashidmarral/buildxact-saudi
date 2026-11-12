<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * See the create_party_period_closures_table migration. A structured
 * history of every close/reopen action against a Client's or Supplier's
 * own lock_date column — not the enforcement mechanism itself.
 */
class PartyPeriodClosure extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'party_type', 'party_id', 'action', 'lock_date', 'performed_by', 'reason'];

    protected function casts(): array
    {
        return ['lock_date' => 'date'];
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

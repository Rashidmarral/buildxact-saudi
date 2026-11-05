<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * See the create_period_closures_table migration. A structured history
 * of every close/reopen action against Company::accounting_lock_date —
 * not the enforcement mechanism itself.
 */
class PeriodClosure extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'action', 'lock_date', 'performed_by', 'reason'];

    protected function casts(): array
    {
        return ['lock_date' => 'date'];
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real service event for a MachineryAsset — see this table's own
 * migration for how it relates to MachineryAsset.next_maintenance_due_date.
 */
class MachineryMaintenanceLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'machinery_asset_id', 'service_type', 'performed_at',
        'expense_id', 'next_due_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

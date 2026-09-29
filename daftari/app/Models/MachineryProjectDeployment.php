<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineryProjectDeployment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'machinery_asset_id', 'project_id', 'start_date', 'end_date',
        'internal_daily_rate', 'operator_employee_id', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'internal_daily_rate' => 'decimal:2',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Notional only — see the migration's docblock for why this is never
     * summed into Project::costs()/cashPaid().
     */
    public function notionalCost(): float
    {
        if (! $this->internal_daily_rate) {
            return 0.0;
        }

        $end = $this->end_date ?? now();
        $days = max(1, $this->start_date->diffInDays($end) + 1);

        return round((float) $this->internal_daily_rate * $days, 2);
    }
}

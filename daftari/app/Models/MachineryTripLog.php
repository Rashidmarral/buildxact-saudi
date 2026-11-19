<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day's real use of a machine/vehicle — who had it, where, and why.
 * See this table's own migration for why it has no distance/odometer
 * fields.
 */
class MachineryTripLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'machinery_asset_id', 'operator_employee_id', 'project_id',
        'trip_date', 'purpose', 'from_location', 'to_location', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWorkOrder extends Model
{
    use BelongsToCompany;

    public const KINDS = ['preventive', 'breakdown', 'installation', 'inspection', 'other'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const STATUSES = ['open', 'assigned', 'in_progress', 'completed', 'closed', 'cancelled'];

    protected $fillable = [
        'company_id', 'project_id', 'work_order_number', 'kind', 'priority', 'title',
        'machinery_asset_id', 'description', 'raised_date', 'scheduled_at', 'duration_minutes',
        'technician_employee_id', 'site_address', 'billable', 'bill_amount', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'raised_date' => 'date',
            'scheduled_at' => 'datetime',
            'billable' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['completed', 'closed', 'cancelled'], true)
            && $this->scheduled_at?->isPast();
    }
}

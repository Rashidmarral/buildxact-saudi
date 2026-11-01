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
        'internal_daily_rate', 'operator_employee_id', 'status', 'notes', 'created_by', 'project_cost_id',
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

    public function projectCost(): BelongsTo
    {
        return $this->belongsTo(ProjectCost::class);
    }

    /** Days the machine was actually deployed on this project, for utilization stats. */
    public function activeDays(): int
    {
        $end = $this->end_date ?? now();

        return max(1, $this->start_date->diffInDays($end) + 1);
    }

    /**
     * rate x days for this deployment. Once the deployment has ended (a
     * final day count is known), postCost() below turns this into a
     * real ProjectCost ledger row — see that method's docblock.
     */
    public function notionalCost(): float
    {
        if (! $this->internal_daily_rate) {
            return 0.0;
        }

        return round((float) $this->internal_daily_rate * $this->activeDays(), 2);
    }

    /**
     * "Hamly's Equipment tab on a project actually posts machinery usage
     * as a real cost against that project ... make equipment usage post
     * real project cost" (user-confirmed, replacing this deployment's
     * previous notional-only behavior). Creates — or, if already posted,
     * updates — a ProjectCost row (cost_head 'equipment') for this
     * deployment's final cost, which Project::costs() then includes.
     * Only meaningful once the deployment has ended: an ongoing
     * deployment's cost isn't final yet (it would keep growing every
     * time this ran), so it posts nothing until end_date is set. A
     * deployment with no internal_daily_rate has no cost to post.
     */
    public function postCost(): void
    {
        if (! $this->internal_daily_rate || ! $this->end_date) {
            return;
        }

        $amount = $this->notionalCost();
        $attributes = [
            'cost_head' => 'equipment',
            'cost_date' => $this->end_date,
            'description' => __('Machinery :code on :project (:days days)', [
                'code' => $this->machinery->asset_code, 'project' => $this->project->name, 'days' => $this->activeDays(),
            ]),
            'amount' => $amount,
            'billable' => true,
            'created_by' => $this->created_by,
        ];

        if ($this->projectCost) {
            $this->projectCost->update($attributes);
        } else {
            $cost = $this->project->projectCosts()->create($attributes);
            $this->update(['project_cost_id' => $cost->id]);
        }
    }
}

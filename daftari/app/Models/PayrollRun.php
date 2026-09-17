<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'run_number', 'period_month', 'period_year', 'pay_date', 'status',
        'total_gross', 'total_gosi_employee', 'total_gosi_employer', 'total_other_deductions', 'total_net',
        'notes', 'created_by', 'approved_by', 'approved_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'pay_date' => 'date',
            'total_gross' => 'decimal:2',
            'total_gosi_employee' => 'decimal:2',
            'total_gosi_employer' => 'decimal:2',
            'total_other_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->total_gross = $items->sum('gross_salary');
        $this->total_gosi_employee = $items->sum('gosi_employee_contribution');
        $this->total_gosi_employer = $items->sum('gosi_employer_contribution');
        $this->total_other_deductions = $items->sum('other_deductions');
        $this->total_net = $items->sum('net_salary');

        $this->save();
    }
}

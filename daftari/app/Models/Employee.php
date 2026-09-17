<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'employee_number', 'full_name', 'full_name_ar',
        'national_id', 'nationality', 'is_saudi', 'date_of_birth', 'gender',
        'mobile', 'email', 'address', 'job_title', 'department',
        'hire_date', 'termination_date', 'status',
        'iban', 'bank_name', 'gosi_subscription_number',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_saudi' => 'boolean',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payrollRunItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function endOfServiceSettlements(): HasMany
    {
        return $this->hasMany(EndOfServiceSettlement::class);
    }

    public function grossSalary(): float
    {
        return (float) $this->basic_salary + (float) $this->housing_allowance
            + (float) $this->transport_allowance + (float) $this->other_allowance;
    }
}

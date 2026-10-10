<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndOfServiceSettlement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'hire_date', 'termination_date', 'reason',
        'years_of_service', 'last_basic_salary', 'gratuity_days', 'gratuity_amount',
        'entitlement_fraction', 'status', 'paid_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'years_of_service' => 'decimal:2',
            'last_basic_salary' => 'decimal:2',
            'gratuity_days' => 'decimal:2',
            'gratuity_amount' => 'decimal:2',
            'entitlement_fraction' => 'decimal:3',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

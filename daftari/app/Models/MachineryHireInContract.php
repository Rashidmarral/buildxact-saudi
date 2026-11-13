<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MachineryHireInContract extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'supplier_id', 'supplier_name', 'supplier_cr_number', 'supplier_phone',
        'contract_number', 'equipment_description', 'equipment_category', 'plate_or_chassis_number',
        'start_date', 'end_date', 'rate', 'rate_type', 'rate_unit_label',
        'operator_included', 'operator_name', 'fuel_responsibility', 'project_id',
        'delivery_condition_notes', 'return_condition_notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'operator_included' => 'boolean',
            'rate' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'machinery_hire_in_contract_id');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(CompanyLetter::class, 'machinery_hire_in_contract_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function supplierDisplayName(): string
    {
        return $this->supplier?->display_name ?? $this->supplier_name ?? __('Unnamed supplier');
    }

    public function totalCost(): float
    {
        return (float) $this->expenses()->where('status', 'approved')->sum('gross_amount');
    }
}

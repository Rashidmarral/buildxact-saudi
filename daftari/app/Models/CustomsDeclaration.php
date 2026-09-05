<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomsDeclaration extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'supplier_id', 'created_by', 'declaration_number', 'declaration_date',
        'port_of_entry', 'customs_value', 'customs_duty', 'vat_rate', 'vat_amount', 'sadad_reference',
        'landed_cost_allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'date',
            'landed_cost_allocated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'customs_declaration_bill');
    }

    public function vatBase(): float
    {
        return (float) $this->customs_value + (float) $this->customs_duty;
    }
}

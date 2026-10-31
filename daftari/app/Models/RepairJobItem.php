<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJobItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'repair_job_id', 'item_id', 'description',
        'quantity', 'unit_price', 'vat_rate', 'core_exchange_credit', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'core_exchange_credit' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lineTotal(): float
    {
        return round(((float) $this->quantity * (float) $this->unit_price) - (float) $this->core_exchange_credit, 2);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenterLink extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'cost_center_id', 'entity_type', 'entity_id', 'allocation_percent'];

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function entity(): ?Model
    {
        return match ($this->entity_type) {
            'invoice' => Invoice::find($this->entity_id),
            default => null,
        };
    }
}

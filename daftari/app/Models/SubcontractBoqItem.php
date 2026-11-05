<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractBoqItem extends Model
{
    protected $fillable = ['subcontract_id', 'description', 'quantity', 'unit', 'rate', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }
}

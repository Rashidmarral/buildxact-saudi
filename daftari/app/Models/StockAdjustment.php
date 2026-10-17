<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'item_id', 'warehouse_id', 'created_by', 'type',
        'quantity', 'reason', 'date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function signedQuantity(): float
    {
        return $this->type === 'increase' ? (float) $this->quantity : -1 * (float) $this->quantity;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'item_id', 'from_warehouse_id', 'to_warehouse_id', 'created_by',
        'quantity', 'date', 'status', 'received_at', 'received_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'received_at' => 'datetime'];
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }
}

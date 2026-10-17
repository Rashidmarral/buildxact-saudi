<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An alternate unit an item can be sold/bought in, e.g. item "Asphalt" has
 * base unit "Ton" and an alternate unit "Bag" with conversion_factor 40
 * (1 Ton = 40 Bags). No company scope of its own — always reached through
 * its parent Item, which is already company-scoped.
 */
class ItemUnit extends Model
{
    protected $fillable = ['item_id', 'unit_id', 'conversion_factor', 'unit_price'];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'unit_price' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

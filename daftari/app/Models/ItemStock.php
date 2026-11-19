<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStock extends Model
{
    use BelongsToCompany;

    protected $fillable = ['item_id', 'warehouse_id', 'quantity', 'low_stock_notified_at'];

    protected function casts(): array
    {
        return ['low_stock_notified_at' => 'datetime'];
    }

    /**
     * BelongsToCompany's own creating() listener only fills company_id
     * from the current Auth user, which is a no-op whenever a stock row
     * is created outside an authenticated web request (a console command
     * — or, just as commonly, test setup code that seeds stock before
     * calling actingAs()). item_id unambiguously determines the company
     * either way, so fall back to it instead of leaving company_id null
     * and silently defeating the scope this trait exists to add.
     */
    protected static function booted(): void
    {
        static::creating(function (ItemStock $stock) {
            if (empty($stock->company_id) && $stock->item_id) {
                $stock->company_id = Item::withoutGlobalScopes()->find($stock->item_id)?->company_id;
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

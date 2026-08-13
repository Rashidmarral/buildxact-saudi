<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'description', 'sku', 'unit',
        'unit_price', 'vat_rate', 'is_active', 'track_inventory', 'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function totalStock(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToCompany;

    public const UNIT_CODES = [
        'PCE' => 'Piece',
        'KGM' => 'Kilogram',
        'GRM' => 'Gram',
        'LTR' => 'Litre',
        'MTR' => 'Metre',
        'MTK' => 'Square metre',
        'MTQ' => 'Cubic metre',
        'BX' => 'Box',
        'CT' => 'Carton',
        'PK' => 'Pack',
        'HUR' => 'Hour',
        'DAY' => 'Day',
        'MON' => 'Month',
        'EA' => 'Each',
    ];

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'description', 'sku', 'barcode', 'category',
        'expiry_date', 'item_type', 'unit', 'unit_code', 'image_path',
        'unit_price', 'purchase_price', 'vat_rate', 'is_active', 'track_inventory', 'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'expiry_date' => 'date',
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

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'description', 'sku', 'unit',
        'unit_price', 'vat_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

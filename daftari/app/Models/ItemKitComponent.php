<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemKitComponent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'kit_item_id', 'component_item_id', 'quantity'];

    public function kitItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'kit_item_id');
    }

    public function componentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}

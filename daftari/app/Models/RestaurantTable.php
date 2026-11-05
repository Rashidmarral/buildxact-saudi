<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'area', 'name', 'seats', 'status'];

    protected function casts(): array
    {
        return ['seats' => 'integer'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'table_id');
    }

    public function openOrder(): ?RestaurantOrder
    {
        return $this->orders()->whereNotIn('status', ['completed', 'cancelled'])->latest('id')->first();
    }
}

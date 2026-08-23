<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'name_ar', 'symbol', 'code'];

    public function itemUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function nameFor(string $locale): string
    {
        if ($locale === 'ar' && $this->name_ar) {
            return $this->name_ar;
        }

        return $this->name;
    }

    public function label(): string
    {
        return $this->symbol ? "{$this->name} ({$this->symbol})" : $this->name;
    }
}

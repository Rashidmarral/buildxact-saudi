<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'branch_id', 'name', 'name_ar', 'address', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Warehouse $warehouse) {
            if (empty($warehouse->code) && $warehouse->company_id) {
                $warehouse->code = static::generateCode($warehouse->company_id);
            }
        });
    }

    public static function generateCode(int $companyId): string
    {
        do {
            $code = 'WH-'.random_int(100000, 999999);
        } while (static::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists());

        return $code;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }
}

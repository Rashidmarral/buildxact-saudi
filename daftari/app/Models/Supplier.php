<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'supplier_code', 'name', 'name_ar', 'vat_number', 'cr_number',
        'email', 'phone', 'address', 'city', 'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if (empty($supplier->supplier_code) && $supplier->company_id) {
                $supplier->supplier_code = static::generateCode($supplier->company_id);
            }
        });
    }

    public static function generateCode(int $companyId): string
    {
        do {
            $code = 'SUP-'.random_int(100000, 999999);
        } while (static::withoutGlobalScopes()->where('company_id', $companyId)->where('supplier_code', $code)->exists());

        return $code;
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToCompany, HasCustomFields;

    public const CUSTOM_FIELD_ENTITY_TYPE = 'supplier';

    protected $fillable = [
        'company_id', 'supplier_code', 'type', 'name', 'name_ar', 'contact_name', 'vat_number',
        'cr_number', 'initial_balance', 'email', 'phone', 'mobile', 'address_line_1',
        'address_line_2', 'district', 'city', 'postal_code', 'state', 'country', 'notes',
        'is_resident', 'lead_source', 'next_follow_up_date', 'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'is_resident' => 'boolean',
            'next_follow_up_date' => 'date',
            'last_contacted_at' => 'datetime',
        ];
    }

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

    public function customsDeclarations(): HasMany
    {
        return $this->hasMany(CustomsDeclaration::class);
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            $this->district,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }
}

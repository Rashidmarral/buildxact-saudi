<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'created_by', 'account_id', 'bank_account_id', 'asset_code', 'name', 'category',
        'acquisition_date', 'acquisition_cost', 'salvage_value', 'useful_life_years',
        'depreciation_method', 'accumulated_depreciation', 'status', 'disposed_at', 'disposal_proceeds', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'disposed_at' => 'date',
            'acquisition_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'disposal_proceeds' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FixedAsset $asset) {
            if (empty($asset->asset_code) && $asset->company_id) {
                $asset->asset_code = static::generateAssetCode($asset->company_id);
            }
        });
    }

    public static function generateAssetCode(int $companyId): string
    {
        do {
            $code = 'AST-'.random_int(100000, 999999);
        } while (static::withoutGlobalScopes()->where('company_id', $companyId)->where('asset_code', $code)->exists());

        return $code;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function depreciableBase(): float
    {
        return max(0, (float) $this->acquisition_cost - (float) $this->salvage_value);
    }

    /**
     * Even split across the asset's useful life in months — the only
     * method this register supports (see the migration's note on why).
     */
    public function monthlyDepreciation(): float
    {
        $months = max(1, $this->useful_life_years * 12);

        return round($this->depreciableBase() / $months, 2);
    }

    public function netBookValue(): float
    {
        return round((float) $this->acquisition_cost - (float) $this->accumulated_depreciation, 2);
    }

    public function remainingDepreciable(): float
    {
        return max(0, $this->depreciableBase() - (float) $this->accumulated_depreciation);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->remainingDepreciable() <= 0.005;
    }
}

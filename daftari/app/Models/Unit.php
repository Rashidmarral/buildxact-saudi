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

    /**
     * A brand-new company has zero units, which leaves every item's base
     * unit unset and the unit picker on invoice/quotation/bill/PO forms
     * permanently disabled (it only enables once the selected item has at
     * least one unit). `code` uses the UN/ECE Recommendation 20 codes
     * ZATCA's invoice XML expects, so items created against these
     * defaults are already ZATCA-unit-code-correct with no extra step.
     */
    public static function seedDefaults(int $companyId): void
    {
        $defaults = [
            ['name' => 'Piece', 'name_ar' => 'قطعة', 'symbol' => 'pc', 'code' => 'PCE'],
            ['name' => 'Kilogram', 'name_ar' => 'كيلوجرام', 'symbol' => 'kg', 'code' => 'KGM'],
            ['name' => 'Gram', 'name_ar' => 'جرام', 'symbol' => 'g', 'code' => 'GRM'],
            ['name' => 'Liter', 'name_ar' => 'لتر', 'symbol' => 'L', 'code' => 'LTR'],
            ['name' => 'Meter', 'name_ar' => 'متر', 'symbol' => 'm', 'code' => 'MTR'],
            ['name' => 'Box', 'name_ar' => 'صندوق', 'symbol' => 'box', 'code' => 'BX'],
            ['name' => 'Carton', 'name_ar' => 'كرتون', 'symbol' => 'ctn', 'code' => 'CT'],
            ['name' => 'Dozen', 'name_ar' => 'دزينة', 'symbol' => 'dz', 'code' => 'DZN'],
            ['name' => 'Pack', 'name_ar' => 'عبوة', 'symbol' => 'pk', 'code' => 'PK'],
            ['name' => 'Hour', 'name_ar' => 'ساعة', 'symbol' => 'hr', 'code' => 'HUR'],
            ['name' => 'Day', 'name_ar' => 'يوم', 'symbol' => 'day', 'code' => 'DAY'],
            ['name' => 'Service', 'name_ar' => 'خدمة', 'symbol' => null, 'code' => 'E48'],
        ];

        foreach ($defaults as $unit) {
            static::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $unit['code']],
                $unit + ['company_id' => $companyId]
            );
        }
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A Withholding Tax category and its rate — e.g. "Technical/consulting
 * services" at 5%, "Royalties" at 15% — applied to payments made to
 * non-resident suppliers. Company-scoped and editable like TaxRate,
 * because WHT rates are set by Saudi tax law (currently ZATCA-administered)
 * and can change; seedDefaults() ships a representative starting set that
 * should be checked against the current regulation, not treated as fixed.
 */
class WhtRate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function seedDefaults(int $companyId): void
    {
        $defaults = [
            ['code' => 'technical_consulting', 'name' => 'Technical & consulting services', 'name_ar' => 'خدمات فنية واستشارية', 'rate' => 5.00],
            ['code' => 'management_fees', 'name' => 'Management fees', 'name_ar' => 'أتعاب إدارية', 'rate' => 20.00],
            ['code' => 'royalties', 'name' => 'Royalties', 'name_ar' => 'إتاوات', 'rate' => 15.00],
            ['code' => 'rent', 'name' => 'Rent', 'name_ar' => 'إيجار', 'rate' => 5.00],
            ['code' => 'interest', 'name' => 'Loan / interest charges', 'name_ar' => 'فوائد قروض', 'rate' => 5.00],
            ['code' => 'insurance', 'name' => 'Insurance & reinsurance premiums', 'name_ar' => 'أقساط تأمين وإعادة تأمين', 'rate' => 5.00],
            ['code' => 'telecom_freight', 'name' => 'International telecom, air/sea freight', 'name_ar' => 'اتصالات دولية وشحن جوي وبحري', 'rate' => 5.00],
            ['code' => 'other', 'name' => 'Other payments', 'name_ar' => 'مدفوعات أخرى', 'rate' => 15.00],
        ];

        foreach ($defaults as $rate) {
            static::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $rate['code']],
                $rate + ['company_id' => $companyId, 'is_active' => true]
            );
        }
    }
}

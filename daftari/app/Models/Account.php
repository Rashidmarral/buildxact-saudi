<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'type', 'normal_balance', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The standard chart of accounts seeded for every new company —
     * mirrors what Account Mappings (see AccountMapping::seedDefaults())
     * points its business keys at by default.
     */
    public static function systemAccounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash', 'name_ar' => 'النقدية', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Bank', 'name_ar' => 'البنك', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'name_ar' => 'الذمم المدينة', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1250', 'name' => 'AR – Retentions Receivable', 'name_ar' => 'ذمم مدينة - محتجزات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'VAT Input', 'name_ar' => 'ضريبة القيمة المضافة المدخلات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1310', 'name' => 'VAT Input – Imports', 'name_ar' => 'ضريبة مدخلات - الواردات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1400', 'name' => 'Inventory', 'name_ar' => 'المخزون', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'name_ar' => 'الذمم الدائنة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'VAT Output', 'name_ar' => 'ضريبة القيمة المضافة المخرجات', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2110', 'name' => 'VAT Output – Reverse Charge', 'name_ar' => 'ضريبة مخرجات - آلية الاحتساب العكسي', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2150', 'name' => 'Customs Payable', 'name_ar' => 'الجمارك المستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Retentions Payable', 'name_ar' => 'محتجزات مستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => "Owner's Equity", 'name_ar' => 'حقوق الملكية', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'name_ar' => 'إيرادات المبيعات', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4100', 'name' => 'Sales Discounts', 'name_ar' => 'خصومات المبيعات', 'type' => 'revenue', 'normal_balance' => 'debit'],
            ['code' => '4150', 'name' => 'Sales Returns', 'name_ar' => 'مردودات المبيعات', 'type' => 'revenue', 'normal_balance' => 'debit'],
            ['code' => '4200', 'name' => 'Other Income', 'name_ar' => 'إيرادات أخرى', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5100', 'name' => 'Operating Expenses', 'name_ar' => 'مصاريف تشغيلية', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5200', 'name' => 'Inventory Adjustment', 'name_ar' => 'تسوية المخزون', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5300', 'name' => 'Zakat / Income Tax', 'name_ar' => 'الزكاة / ضريبة الدخل', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '9000', 'name' => 'Rounding', 'name_ar' => 'فروقات التقريب', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '9100', 'name' => 'FX Gain', 'name_ar' => 'أرباح فروقات العملة', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '9200', 'name' => 'FX Loss', 'name_ar' => 'خسائر فروقات العملة', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '9300', 'name' => 'Cash Shortage', 'name_ar' => 'عجز نقدي', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];
    }

    public static function seedSystemAccounts(int $companyId): void
    {
        foreach (self::systemAccounts() as $account) {
            self::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                $account + ['company_id' => $companyId, 'is_system' => true]
            );
        }
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}

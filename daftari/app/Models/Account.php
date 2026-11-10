<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'type', 'parent_account_id', 'normal_balance', 'is_system', 'is_active',
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
            ['code' => '1150', 'name' => 'POS Card Clearing', 'name_ar' => 'حساب وسيط تحصيل نقاط البيع', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'name_ar' => 'الذمم المدينة', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1250', 'name' => 'AR – Retentions Receivable', 'name_ar' => 'ذمم مدينة - محتجزات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1260', 'name' => 'Due from Employees (Advances)', 'name_ar' => 'ذمم موظفين (سلف)', 'type' => 'asset', 'normal_balance' => 'debit'],
            // A post-dated cheque received settles the customer's
            // receivable immediately (same as a bank transfer would) but
            // isn't real cash yet — it sits here until it actually
            // clears the bank, at which point it reclasses into that
            // bank account's own ledger line (see
            // LedgerPostingService::postReceiptVoucherChequeClearance()).
            ['code' => '1270', 'name' => 'Cheques Received (Under Collection)', 'name_ar' => 'شيكات مستلمة تحت التحصيل', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'VAT Input', 'name_ar' => 'ضريبة القيمة المضافة المدخلات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1310', 'name' => 'VAT Input – Imports', 'name_ar' => 'ضريبة مدخلات - الواردات', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1400', 'name' => 'Inventory', 'name_ar' => 'المخزون', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'name_ar' => 'الأصول الثابتة', 'type' => 'asset', 'normal_balance' => 'debit'],
            // Contra-asset: normal_balance is credit even though the
            // account's type is "asset" — accumulated depreciation reduces
            // the fixed-asset balance, it isn't a liability.
            ['code' => '1550', 'name' => 'Accumulated Depreciation', 'name_ar' => 'مجمع الإهلاك', 'type' => 'asset', 'normal_balance' => 'credit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'name_ar' => 'الذمم الدائنة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'VAT Output', 'name_ar' => 'ضريبة القيمة المضافة المخرجات', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2110', 'name' => 'VAT Output – Reverse Charge', 'name_ar' => 'ضريبة مخرجات - آلية الاحتساب العكسي', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2150', 'name' => 'Customs Payable', 'name_ar' => 'الجمارك المستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Retentions Payable', 'name_ar' => 'محتجزات مستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            // The mirror of 1270 on the payable side: a post-dated
            // cheque we've written settles the supplier's bill
            // immediately, but the cash doesn't actually leave the bank
            // until it clears — see
            // LedgerPostingService::postPaymentVoucherChequeClearance().
            ['code' => '2240', 'name' => 'Cheques Issued (Outstanding)', 'name_ar' => 'شيكات صادرة لم تُصرف بعد', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2250', 'name' => 'Withholding Tax Payable', 'name_ar' => 'ضريبة الاستقطاع المستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2260', 'name' => 'GOSI Payable', 'name_ar' => 'التأمينات الاجتماعية المستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2270', 'name' => 'Salaries Payable', 'name_ar' => 'الرواتب المستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2280', 'name' => 'End-of-Service Provision', 'name_ar' => 'مخصص مكافأة نهاية الخدمة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2290', 'name' => 'Other Payroll Deductions Payable', 'name_ar' => 'استقطاعات رواتب أخرى مستحقة', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => "Owner's Equity", 'name_ar' => 'حقوق الملكية', 'type' => 'equity', 'normal_balance' => 'credit'],
            // Contra-equity: personal/non-business spending that went
            // through a company account, or cash the owner simply drew
            // out — reduces what the owner has invested in the company,
            // it is never a deductible business Operating Expense.
            ['code' => '3100', 'name' => "Owner's Drawings", 'name_ar' => 'مسحوبات المالك', 'type' => 'equity', 'normal_balance' => 'debit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'name_ar' => 'إيرادات المبيعات', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4100', 'name' => 'Sales Discounts', 'name_ar' => 'خصومات المبيعات', 'type' => 'revenue', 'normal_balance' => 'debit'],
            ['code' => '4150', 'name' => 'Sales Returns', 'name_ar' => 'مردودات المبيعات', 'type' => 'revenue', 'normal_balance' => 'debit'],
            ['code' => '4200', 'name' => 'Other Income', 'name_ar' => 'إيرادات أخرى', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5100', 'name' => 'Operating Expenses', 'name_ar' => 'مصاريف تشغيلية', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5150', 'name' => 'Depreciation Expense', 'name_ar' => 'مصروف الإهلاك', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5180', 'name' => 'Bank & Card Processing Fees', 'name_ar' => 'رسوم بنكية ومعالجة البطاقات', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5200', 'name' => 'Inventory Adjustment', 'name_ar' => 'تسوية المخزون', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5300', 'name' => 'Zakat / Income Tax', 'name_ar' => 'الزكاة / ضريبة الدخل', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5350', 'name' => 'Salaries & Wages Expense', 'name_ar' => 'مصروف الرواتب والأجور', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5360', 'name' => 'GOSI Expense (Employer Share)', 'name_ar' => 'مصروف التأمينات الاجتماعية (حصة المنشأة)', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5370', 'name' => 'End-of-Service Expense', 'name_ar' => 'مصروف مكافأة نهاية الخدمة', 'type' => 'expense', 'normal_balance' => 'debit'],
            // Real cash that genuinely left the company but can never be
            // claimed as a deductible business cost (e.g. the price paid
            // to acquire a fake invoice) — still a real loss to the
            // owner, so it stays in the P&L, just separated from
            // Operating Expenses so it's never swept into a VAT/Zakat
            // deductible-expense figure.
            ['code' => '5900', 'name' => 'Non-Deductible Expenses', 'name_ar' => 'مصاريف غير قابلة للخصم', 'type' => 'expense', 'normal_balance' => 'debit'],
            // Contra-expense: a project's own cost ledger (equipment
            // deployments, approved timesheets, manual "add cost" entries
            // — see LedgerPostingService::postProjectCostAllocation())
            // attributes an INTERNAL cost to a project's Cost of Goods
            // Sold for job-costing visibility, without creating a second
            // real expense alongside the one already posted elsewhere
            // (machinery depreciation, payroll). Crediting this instead of
            // reducing COGS directly keeps both sides visible: how much
            // was allocated to projects, and that the company-wide P&L
            // net effect of every such allocation is always zero.
            ['code' => '5950', 'name' => 'Internal Cost Recovery', 'name_ar' => 'استرداد التكلفة الداخلية', 'type' => 'expense', 'normal_balance' => 'credit'],
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    /**
     * Picks the next free code in a reserved sub-range under one of the
     * system cash/bank parents (1001–1099 under 1000 Cash, 1101–1149
     * under 1100 Bank — both ranges sit below the next system code in
     * that family, 1100/1150, so they never collide with
     * systemAccounts()). Used to give each real BankAccount its own
     * dedicated ledger line instead of every bank/cash account sharing
     * the one generic Cash/Bank account — see BankAccount::glAccount().
     */
    public static function nextAvailableCode(int $companyId, int $min, int $max): ?string
    {
        // Fetched and filtered in PHP rather than with a numeric CAST in
        // SQL: `code` is a string column, and a portable numeric cast
        // across both MySQL (production) and SQLite (tests) isn't worth
        // the complexity for a chart of accounts that's always small.
        $used = self::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->pluck('code')
            ->map(fn ($c) => (int) $c)
            ->filter(fn ($c) => $c >= $min && $c <= $max)
            ->all();

        for ($i = $min; $i <= $max; $i++) {
            if (! in_array($i, $used, true)) {
                return (string) $i;
            }
        }

        return null;
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}

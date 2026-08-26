<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'account_id'];

    /**
     * Every business key the ledger posting service (LedgerPostingService)
     * looks up by name, and the system-account code it defaults to. Labels
     * mirror the "Account Mappings" section wording.
     */
    public static function catalog(): array
    {
        return [
            'ACCOUNTS_PAYABLE' => ['label' => 'Accounts payable (suppliers)', 'default_code' => '2000'],
            'ACCOUNTS_RECEIVABLE' => ['label' => 'Accounts receivable (customers)', 'default_code' => '1200'],
            'AR_RETENTION' => ['label' => 'AR — retention', 'default_code' => '1250'],
            'DEFAULT_BANK' => ['label' => 'Default bank', 'default_code' => '1100'],
            'DEFAULT_CASH' => ['label' => 'Default cash', 'default_code' => '1000'],
            'ACCUMULATED_DEPRECIATION_DEFAULT' => ['label' => 'Accumulated depreciation (default)', 'default_code' => '1550'],
            'DEPRECIATION_EXPENSE_DEFAULT' => ['label' => 'Depreciation expense (default)', 'default_code' => '5150'],
            'FIXED_ASSETS_DEFAULT' => ['label' => 'Fixed assets (default)', 'default_code' => '1500'],
            'CASH_SHORTAGE_DEFAULT' => ['label' => 'Cash shortage (default)', 'default_code' => '9300'],
            'COGS_DEFAULT' => ['label' => 'Cost of goods sold (default)', 'default_code' => '5000'],
            'CUSTOMS_PAYABLE' => ['label' => 'Customs payable', 'default_code' => '2150'],
            'FX_GAINS' => ['label' => 'FX gains', 'default_code' => '9100'],
            'FX_LOSSES' => ['label' => 'FX losses', 'default_code' => '9200'],
            'INVENTORY_ADJUSTMENT' => ['label' => 'Inventory adjustment', 'default_code' => '5200'],
            'INVENTORY_ASSET' => ['label' => 'Inventory asset', 'default_code' => '1400'],
            'DEFAULT_OPERATING_EXPENSES' => ['label' => 'Default operating expenses', 'default_code' => '5100'],
            'OTHER_INCOME_DEFAULT' => ['label' => 'Other income (default)', 'default_code' => '4200'],
            'RETENTION_PAYABLE' => ['label' => 'Retention payable', 'default_code' => '2200'],
            'ROUNDING' => ['label' => 'Rounding', 'default_code' => '9000'],
            'DEFAULT_SALES_DISCOUNTS' => ['label' => 'Default sales discounts', 'default_code' => '4100'],
            'SALES_RETURNS_DEFAULT' => ['label' => 'Sales returns (default)', 'default_code' => '4150'],
            'DEFAULT_SALES_REVENUE' => ['label' => 'Default sales revenue', 'default_code' => '4000'],
            'VAT_INPUT' => ['label' => 'VAT input (recoverable)', 'default_code' => '1300'],
            'VAT_INPUT_IMPORTS' => ['label' => 'VAT input — imports', 'default_code' => '1310'],
            'VAT_OUTPUT' => ['label' => 'VAT output (payable)', 'default_code' => '2100'],
            'VAT_OUTPUT_RCM' => ['label' => 'VAT output — reverse charge', 'default_code' => '2110'],
            'ZAKAT_TAX_DEFAULT' => ['label' => 'Zakat / income tax (default)', 'default_code' => '5300'],
        ];
    }

    public static function seedDefaults(int $companyId): void
    {
        $accountsByCode = Account::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->pluck('id', 'code');

        foreach (self::catalog() as $key => $meta) {
            $accountId = $accountsByCode[$meta['default_code']] ?? null;

            if (! $accountId) {
                continue;
            }

            self::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['account_id' => $accountId]
            );
        }
    }

    /**
     * Resolve a business key to the company's currently-mapped account,
     * falling back to the system account at its default code if the
     * mapping row is somehow missing (e.g. seeded before this key existed).
     */
    public static function resolve(int $companyId, string $key): ?Account
    {
        $mapping = self::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($mapping) {
            return $mapping->account;
        }

        $defaultCode = self::catalog()[$key]['default_code'] ?? null;

        return $defaultCode
            ? Account::withoutGlobalScope('company')->where('company_id', $companyId)->where('code', $defaultCode)->first()
            : null;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

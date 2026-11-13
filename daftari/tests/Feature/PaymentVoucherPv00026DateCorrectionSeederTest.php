<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use Database\Seeders\PaymentVoucherPv00026DateCorrectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 12: PV-00026 is dated a full year off
 * (2025-08-01 instead of 2026-08-01) — this corrects the date and
 * reposts its journal entry at the right date, amount unchanged.
 */
class PaymentVoucherPv00026DateCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(array $overrides = []): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Nasir Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $voucher = PaymentVoucher::create(array_merge([
            'company_id' => $company->id,
            'bank_account_id' => $bank->id,
            'party_type' => 'manual',
            'voucher_number' => 'PV-00026',
            'date' => '2025-08-01',
            'payee_name' => 'Abdullah bin Yousef bin Ali Al-Qasimi',
            'amount' => 13000.00,
            'method' => 'cash',
            'notes' => 'Being payment of yard rental charges for thirteen (13) months.',
            'status' => 'issued',
        ], $overrides));

        app(\App\Services\Accounting\LedgerPostingService::class)->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));

        return [$company, $voucher];
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new PaymentVoucherPv00026DateCorrectionSeeder)->run();

        $this->assertSame(0, PaymentVoucher::count());
    }

    public function test_it_errors_cleanly_when_the_voucher_is_missing(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new PaymentVoucherPv00026DateCorrectionSeeder)->run();

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_it_corrects_the_date_and_reposts(): void
    {
        [$company, $voucher] = $this->seedFixture();

        (new PaymentVoucherPv00026DateCorrectionSeeder)->run();

        $voucher->refresh();
        $this->assertSame('2026-08-01', $voucher->date->toDateString());

        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
        $this->assertNotNull($journalEntry);
        $this->assertSame('2026-08-01', $journalEntry->entry_date->toDateString());
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_reseeding_does_not_duplicate_the_repost(): void
    {
        [$company, $voucher] = $this->seedFixture();

        (new PaymentVoucherPv00026DateCorrectionSeeder)->run();
        (new PaymentVoucherPv00026DateCorrectionSeeder)->run();

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }
}

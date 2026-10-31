<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Database\Seeders\HomeExpenseBankTransferReclassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 11 #12: the real SAR 500.00 "For home
 * expenses" transfer from Petty Cash to the owner's personal cash
 * account never posted anything (cash-to-cash transfers don't post) —
 * this records it as a real Owner's Drawing instead.
 */
class HomeExpenseBankTransferReclassificationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(array $overrides = []): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $pettyCash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $rashidCash = BankAccount::create(['company_id' => $company->id, 'name' => 'Rashid Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true]);

        $transfer = BankTransfer::create(array_merge([
            'company_id' => $company->id,
            'from_bank_account_id' => $pettyCash->id,
            'to_bank_account_id' => $rashidCash->id,
            'amount' => 500.00,
            'date' => '2026-10-19',
            'notes' => 'For home expenses',
            'status' => 'issued',
        ], $overrides));

        return [$company, $transfer];
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new HomeExpenseBankTransferReclassificationSeeder)->run();

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_the_real_transfer_posts_nothing_on_its_own(): void
    {
        // Confirms the premise this seeder fixes: cash-to-cash transfers never post.
        [$company, $transfer] = $this->seedFixture();

        app(\App\Services\Accounting\LedgerPostingService::class)->postBankTransfer($transfer);

        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->count());
    }

    public function test_it_posts_the_drawing_without_touching_the_bank_transfer_record(): void
    {
        [$company, $transfer] = $this->seedFixture();

        (new HomeExpenseBankTransferReclassificationSeeder)->run();

        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('source_type', 'bank_transfer')->where('source_id', $transfer->id)->first();
        $this->assertNotNull($journalEntry);

        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        $drawingsLine = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $drawings->id)->first();
        $this->assertNotNull($drawingsLine);
        $this->assertEqualsWithDelta(500.00, (float) $drawingsLine->debit, 0.01);

        $cash = AccountMapping::resolve($company->id, 'DEFAULT_CASH');
        $cashLine = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $cash->id)->first();
        $this->assertNotNull($cashLine);
        $this->assertEqualsWithDelta(500.00, (float) $cashLine->credit, 0.01);

        $transfer->refresh();
        $this->assertSame(500.00, (float) $transfer->amount);
    }

    public function test_it_leaves_an_unmatched_transfer_alone(): void
    {
        [$company] = $this->seedFixture(['amount' => 999.00, 'notes' => 'Unrelated transfer']);

        (new HomeExpenseBankTransferReclassificationSeeder)->run();

        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->count());
    }

    public function test_reseeding_does_not_duplicate_the_posting(): void
    {
        [$company, $transfer] = $this->seedFixture();

        (new HomeExpenseBankTransferReclassificationSeeder)->run();
        (new HomeExpenseBankTransferReclassificationSeeder)->run();

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'bank_transfer')->where('source_id', $transfer->id)->count());
    }
}

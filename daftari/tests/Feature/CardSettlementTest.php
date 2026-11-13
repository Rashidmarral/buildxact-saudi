<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\CardSettlement;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Card/POS Settlement workflow" — the bank actually
 * settling a card/POS clearing balance: fee (MDR) + VAT on fee netted
 * off, the rest deposited. Covers the GL posting (net to bank, fee to
 * expense, VAT to VAT input, gross relieved from clearing), the void
 * reversal, and the fee-exceeds-gross guard.
 */
class CardSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        PaymentMode::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_settlement_posts_net_to_bank_fee_to_expense_vat_to_input_and_relieves_clearing(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Operating Account', 'is_active' => true]);
        $cardMode = PaymentMode::where('company_id', $company->id)->where('code', 'card')->first();
        $clearingAccountId = $cardMode->linked_account_id;

        $response = $this->actingAs($owner)->post(route('app.card-settlements.store'), [
            'payment_mode_id' => $cardMode->id,
            'bank_account_id' => $bankAccount->id,
            'settlement_date' => now()->toDateString(),
            'gross_amount' => 1000,
            'fee_amount' => 25,
            'fee_vat_amount' => 3.75,
            'reference' => 'STL-001',
        ]);

        $response->assertRedirect();
        $settlement = CardSettlement::first();
        $this->assertNotNull($settlement);
        $this->assertSame(971.25, (float) $settlement->net_amount);

        $entry = JournalEntry::where('source_type', 'card_settlement')->where('source_id', $settlement->id)->with('lines.account')->first();
        $this->assertNotNull($entry);

        $bankLine = $entry->lines->firstWhere('account_id', $bankAccount->fresh()->glAccount()->id);
        $this->assertNotNull($bankLine);
        $this->assertSame(971.25, (float) $bankLine->debit);

        $clearingLine = $entry->lines->firstWhere('account_id', $clearingAccountId);
        $this->assertNotNull($clearingLine);
        $this->assertSame(1000.0, (float) $clearingLine->credit);

        $feeAccount = Account::where('company_id', $company->id)->where('code', '5180')->first();
        $feeLine = $entry->lines->firstWhere('account_id', $feeAccount->id);
        $this->assertNotNull($feeLine);
        $this->assertSame(25.0, (float) $feeLine->debit);

        $vatInput = Account::where('company_id', $company->id)->where('code', '1300')->first();
        $vatLine = $entry->lines->firstWhere('account_id', $vatInput->id);
        $this->assertNotNull($vatLine);
        $this->assertSame(3.75, (float) $vatLine->debit);

        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
    }

    public function test_the_fee_and_its_vat_cannot_exceed_the_gross_amount(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Operating Account', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.card-settlements.store'), [
            'bank_account_id' => $bankAccount->id,
            'settlement_date' => now()->toDateString(),
            'gross_amount' => 100,
            'fee_amount' => 80,
            'fee_vat_amount' => 30,
        ]);

        $response->assertSessionHasErrors('fee_amount');
        $this->assertSame(0, CardSettlement::count());
    }

    public function test_voiding_a_settlement_reverses_the_ledger_entry(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Operating Account', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.card-settlements.store'), [
            'bank_account_id' => $bankAccount->id,
            'settlement_date' => now()->toDateString(),
            'gross_amount' => 500,
            'fee_amount' => 10,
        ])->assertRedirect();

        $settlement = CardSettlement::first();

        $this->actingAs($owner)->post(route('app.card-settlements.void', $settlement))->assertRedirect();

        $this->assertSame('void', $settlement->fresh()->status);
        $this->assertTrue(JournalEntry::where('source_type', 'card_settlement_reversal')->where('source_id', $settlement->id)->exists());
    }

    public function test_index_and_pdf_render(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Operating Account', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.card-settlements.store'), [
            'bank_account_id' => $bankAccount->id,
            'settlement_date' => now()->toDateString(),
            'gross_amount' => 250,
            'fee_amount' => 5,
        ])->assertRedirect();

        $settlement = CardSettlement::first();

        $page = $this->actingAs($owner)->get(route('app.card-settlements.index'));
        $page->assertOk()->assertSee('250.00');

        $pdf = $this->actingAs($owner)->get(route('app.card-settlements.pdf', $settlement));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}

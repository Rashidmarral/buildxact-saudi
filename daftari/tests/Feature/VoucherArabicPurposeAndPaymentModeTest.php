<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two reported gaps on the Payment/Receipt Voucher print:
 * 1. The "For" (purpose) line had no Arabic input — both sides of the
 *    bilingual row printed the same English text.
 * 2. The payment-mode checkbox row only ever ticked Cash or Cheque —
 *    Bank Transfer and Card fell through to plain, unchecked text.
 */
class VoucherArabicPurposeAndPaymentModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Voucher Co.', 'slug' => 'voucher-co-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
    }

    public function test_a_distinct_arabic_purpose_can_be_saved_and_prints_on_both_the_show_page_and_the_pdf(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Steel Supplies Co.',
            'bank_account_id' => $account->id, 'date' => now()->toDateString(),
            'amount' => 1500, 'method' => 'bank_transfer',
            'notes' => 'Rebar delivery', 'notes_ar' => 'توريد حديد التسليح',
        ]);

        $voucher = PaymentVoucher::first();
        $this->assertSame('توريد حديد التسليح', $voucher->notes_ar);

        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));
        $show->assertOk();
        $show->assertSee('Rebar delivery');
        $show->assertSee('توريد حديد التسليح');

        $pdf = $this->actingAs($owner)->get(route('app.payment-vouchers.pdf', $voucher));
        $pdf->assertOk();
    }

    public function test_leaving_the_arabic_purpose_blank_falls_back_to_the_english_text(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Steel Supplies Co.',
            'bank_account_id' => $account->id, 'date' => now()->toDateString(),
            'amount' => 1500, 'method' => 'cash', 'notes' => 'Rebar delivery',
        ]);

        $voucher = PaymentVoucher::first();
        $this->assertNull($voucher->notes_ar);

        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));
        $show->assertOk();
        // The English text appears on both sides since no Arabic override was given.
        $show->assertSeeInOrder(['Rebar delivery', 'Rebar delivery']);
    }

    public function test_a_bank_transfer_payment_ticks_the_bank_transfer_box_not_cash_or_card(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Steel Supplies Co.',
            'bank_account_id' => $account->id, 'date' => now()->toDateString(),
            'amount' => 1500, 'method' => 'bank_transfer',
        ]);
        $voucher = PaymentVoucher::first();

        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));
        $show->assertOk();
        $show->assertSee(__('Bank transfer'));
        $show->assertSee('border-slate-800 bg-slate-800 text-white', false);

        $pdf = $this->actingAs($owner)->get(route('app.payment-vouchers.pdf', $voucher));
        $pdf->assertOk();
    }

    public function test_a_card_payment_ticks_the_card_box(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Steel Supplies Co.',
            'bank_account_id' => $account->id, 'date' => now()->toDateString(),
            'amount' => 1500, 'method' => 'card',
        ]);
        $voucher = PaymentVoucher::first();

        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));
        $show->assertOk();
        $show->assertSee(__('Card'));

        $pdf = $this->actingAs($owner)->get(route('app.payment-vouchers.pdf', $voucher));
        $pdf->assertOk();
    }
}

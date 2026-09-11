<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WhtRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding: Withholding Tax on payments to non-resident suppliers had
 * no support at all — no residency flag, no rate table, nothing posting to
 * a WHT payable liability. A non-resident supplier's bill can now name a
 * WHT category; the first payment against it withholds the tax instead of
 * paying it to the supplier, crediting WHT Payable for later remittance.
 */
class WithholdingTaxTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'WHT Co.', 'slug' => 'wht-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        WhtRate::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function nonResidentBillWithWht(User $owner): array
    {
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Foreign Consultant', 'is_resident' => false]);
        $whtRate = WhtRate::where('company_id', $owner->company_id)->where('code', 'technical_consulting')->first();

        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'draft', 'bill_date' => now()->toDateString(), 'wht_rate_id' => $whtRate->id,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'wht_amount' => 50,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        return [$supplier, $bill, $whtRate];
    }

    public function test_creating_a_bill_for_a_non_resident_supplier_computes_wht_amount(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Foreign Consultant', 'is_resident' => false]);
        $whtRate = WhtRate::where('company_id', $owner->company_id)->where('code', 'technical_consulting')->first();

        $response = $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'wht_rate_id' => $whtRate->id, 'bill_date' => now()->toDateString(),
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $bill = Bill::latest('id')->first();
        $this->assertSame(50.0, (float) $bill->wht_amount);
        $this->assertSame($whtRate->id, $bill->wht_rate_id);
    }

    public function test_a_resident_suppliers_bill_never_withholds_even_if_a_category_is_submitted(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Local Vendor', 'is_resident' => true]);
        $whtRate = WhtRate::where('company_id', $owner->company_id)->where('code', 'technical_consulting')->first();

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'wht_rate_id' => $whtRate->id, 'bill_date' => now()->toDateString(),
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0]],
        ])->assertSessionDoesntHaveErrors();

        $bill = Bill::latest('id')->first();
        $this->assertSame(0.0, (float) $bill->wht_amount);
        $this->assertNull($bill->wht_rate_id);
    }

    public function test_paying_a_wht_bill_withholds_tax_and_posts_three_ledger_lines(): void
    {
        $owner = $this->makeOwner();
        [$supplier, $bill] = $this->nonResidentBillWithWht($owner);
        $bank = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Bank', 'type' => 'bank', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bank->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'bill_id' => $bill->id, 'date' => now()->toDateString(), 'payee_name' => $supplier->name,
            'amount' => 950, 'method' => 'bank_transfer',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $bill->refresh();
        $this->assertTrue($bill->wht_withheld);
        $this->assertSame(1000.0, (float) $bill->amount_paid);

        $entry = JournalEntry::where('source_type', 'payment_voucher')->latest('id')->first();
        $ap = Account::where('company_id', $owner->company_id)->where('code', '2000')->first();
        $bankAccount = Account::where('company_id', $owner->company_id)->where('code', '1100')->first();
        $whtPayable = Account::where('company_id', $owner->company_id)->where('code', '2250')->first();

        $this->assertSame(1000.0, (float) $entry->lines()->where('account_id', $ap->id)->sum('debit'));
        $this->assertSame(950.0, (float) $entry->lines()->where('account_id', $bankAccount->id)->sum('credit'));
        $this->assertSame(50.0, (float) $entry->lines()->where('account_id', $whtPayable->id)->sum('credit'));
    }

    public function test_a_second_voucher_on_the_same_bill_never_withholds_again(): void
    {
        $owner = $this->makeOwner();
        [$supplier, $bill] = $this->nonResidentBillWithWht($owner);
        $bank = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Bank', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bank->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'bill_id' => $bill->id, 'date' => now()->toDateString(), 'payee_name' => $supplier->name,
            'amount' => 950, 'method' => 'bank_transfer',
        ]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bank->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'bill_id' => $bill->id, 'date' => now()->toDateString(), 'payee_name' => $supplier->name,
            'amount' => 10, 'method' => 'bank_transfer',
        ]);

        $second = PaymentVoucher::latest('id')->first();
        $this->assertSame(0.0, (float) $second->wht_amount);
    }

    public function test_voiding_the_withholding_voucher_reopens_the_bill_for_withholding(): void
    {
        $owner = $this->makeOwner();
        [$supplier, $bill] = $this->nonResidentBillWithWht($owner);
        $bank = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Bank', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bank->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'bill_id' => $bill->id, 'date' => now()->toDateString(), 'payee_name' => $supplier->name,
            'amount' => 950, 'method' => 'bank_transfer',
        ]);

        $voucher = PaymentVoucher::latest('id')->first();
        $this->actingAs($owner)->post(route('app.payment-vouchers.void', $voucher));

        $bill->refresh();
        $this->assertFalse($bill->wht_withheld);
        $this->assertSame(0.0, (float) $bill->amount_paid);
    }

    public function test_marking_a_supplier_non_resident_via_the_form_persists_it(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.suppliers.store'), [
            'type' => 'company', 'name' => 'Overseas Supplier', 'is_non_resident' => '1',
        ])->assertSessionDoesntHaveErrors();

        $supplier = Supplier::latest('id')->first();
        $this->assertFalse($supplier->is_resident);
    }

    public function test_a_supplier_defaults_to_resident_when_the_checkbox_is_left_unchecked(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.suppliers.store'), [
            'type' => 'company', 'name' => 'Local Supplier',
        ])->assertSessionDoesntHaveErrors();

        $supplier = Supplier::latest('id')->first();
        $this->assertTrue($supplier->is_resident);
    }

    public function test_wht_rate_settings_screen_supports_full_crud(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.wht-rates.store'), [
            'code' => 'custom_category', 'name' => 'Custom Category', 'rate' => 7.5, 'is_active' => '1',
        ])->assertSessionDoesntHaveErrors();

        $rate = WhtRate::where('code', 'custom_category')->first();
        $this->assertNotNull($rate);
        $this->assertSame(7.5, (float) $rate->rate);

        $this->actingAs($owner)->put(route('app.wht-rates.update', $rate), [
            'code' => 'custom_category', 'name' => 'Custom Category', 'rate' => 10, 'is_active' => '1',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(10.0, (float) $rate->fresh()->rate);

        $this->actingAs($owner)->delete(route('app.wht-rates.destroy', $rate))->assertSessionDoesntHaveErrors();
        $this->assertNull(WhtRate::find($rate->id));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PrepaymentInvoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\RecordRetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Compliance gap found while auditing ZATCA record-keeping: Saudi VAT
 * Implementing Regulations Article 66 requires accounting records to be
 * kept at least 6 years, but nothing in the app enforced it — a posted
 * expense, income record, customs declaration, or issued prepayment
 * invoice could be hard-deleted at any age with only whatever document-
 * specific guard that controller happened to already have (none of
 * which know anything about retention itself). A draft/pending record
 * that never actually posted to the ledger isn't yet part of "the
 * books" and stays freely deletable, matching how each of these
 * controllers already treats drafts for other reasons.
 */
class RecordRetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Retention Co.', 'slug' => 'retention-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_policy_treats_anything_under_six_years_old_as_protected(): void
    {
        $this->assertTrue(RecordRetentionPolicy::isWithinRetentionWindow(now()));
        $this->assertTrue(RecordRetentionPolicy::isWithinRetentionWindow(now()->subYears(5)->subMonths(11)));
        $this->assertFalse(RecordRetentionPolicy::isWithinRetentionWindow(now()->subYears(10)));
    }

    /**
     * Audit finding COMP-01: the window counts 6 years from the END of
     * the tax quarter containing the document's date, per Article 66 —
     * not from the document's own date directly. With "now" fixed at
     * 2026-10-25: a document dated 2020-10-20 is barely over 6 raw years
     * old (old, incorrect logic would already call it expired), but it
     * falls in Q4 2020 (ends 2020-12-31) — and 2020-12-31 is still under
     * 6 years before 2026-10-25, so Article 66 actually still protects
     * it. A document dated 2020-01-02 (Q1 2020, ends 2020-03-31 — well
     * over 6 years before 2026-10-25 either way) is correctly expired
     * under both the old and the corrected logic.
     */
    public function test_the_window_counts_from_the_end_of_the_tax_quarter_not_the_raw_document_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 25));

        try {
            $this->assertTrue(
                RecordRetentionPolicy::isWithinRetentionWindow(Carbon::create(2020, 10, 20)),
                'A document dated just past the raw 6-year mark must still be protected once its quarter end is under 6 years old.'
            );
            $this->assertFalse(RecordRetentionPolicy::isWithinRetentionWindow(Carbon::create(2020, 1, 2)));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_recent_approved_expense_cannot_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $expense = Expense::create([
            'company_id' => $owner->company_id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->subYears(2)->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense));

        $response->assertSessionHasErrors('expense');
        $this->assertNotNull(Expense::find($expense->id));
    }

    public function test_an_unapproved_expense_is_unaffected_by_retention(): void
    {
        $owner = $this->makeOwner();
        $expense = Expense::create([
            'company_id' => $owner->company_id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense));

        $response->assertRedirect();
        $this->assertNull(Expense::find($expense->id));
    }

    public function test_an_expense_past_the_retention_window_can_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $expense = Expense::create([
            'company_id' => $owner->company_id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->subYears(7)->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense));

        $response->assertRedirect();
        $this->assertNull(Expense::find($expense->id));
    }

    public function test_a_recent_income_record_cannot_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $income = Income::create([
            'company_id' => $owner->company_id, 'gross_amount' => 1000, 'amount' => 1000, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'income_date' => now()->subYear()->toDateString(), 'payer_name' => 'Scrap buyer',
        ]);

        $response = $this->actingAs($owner)->delete(route('app.incomes.destroy', $income));

        $response->assertSessionHasErrors('income');
        $this->assertNotNull(Income::find($income->id));
    }

    public function test_a_recent_customs_declaration_cannot_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $declaration = CustomsDeclaration::create([
            'company_id' => $owner->company_id, 'declaration_number' => 'CD-0001', 'declaration_date' => now()->subYear()->toDateString(),
            'customs_value' => 1000, 'customs_duty' => 50, 'vat_rate' => 15, 'vat_amount' => 157.5,
        ]);

        $response = $this->actingAs($owner)->delete(route('app.customs-declarations.destroy', $declaration));

        $response->assertSessionHasErrors('customs_declaration');
        $this->assertNotNull(CustomsDeclaration::find($declaration->id));
    }

    public function test_a_recent_issued_prepayment_invoice_cannot_be_deleted_even_unsynced(): void
    {
        $owner = $this->makeOwner();
        TaxRate::seedDefaults($owner->company_id);
        $rate = TaxRate::where('company_id', $owner->company_id)->where('type', TaxRate::TYPE_STANDARD)->first();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Advance Client']);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'tax_rate_id' => $rate->id, 'created_by' => $owner->id,
            'prepayment_number' => 'PPI-00001', 'type' => 'standard', 'status' => 'issued',
            'issue_date' => now()->subMonth()->toDateString(), 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->delete(route('app.prepayment-invoices.destroy', $prepayment));

        $response->assertSessionHasErrors('prepayment');
        $this->assertNotNull(PrepaymentInvoice::find($prepayment->id));
    }
}

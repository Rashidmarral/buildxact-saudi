<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractReportCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "shahid payment vouchers 124800 shows detail wise in report other
 * alhazera and ashraf payment shows as Sub Base 146.00 trip 500.00
 * unit price 73,000.00 total this is ashraf and aljazera payment...
 * add functionality like if i need to update quantities of item it
 * will applied to whole like recalculate" — a stored, per-subcontract
 * report category list controlling the printed Receiving Payments
 * Report: an "itemized" category still lists every matching real
 * payment; a "measured" category collapses to one quantity × rate
 * summary line, and editing its quantity recalculates its amount.
 */
class SubcontractReportCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubcontractWithPayments(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);

        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'contract_value' => 200000, 'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Full', 'percentage' => 100, 'amount' => 200000]);

        $shahid = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $supplier->id,
            'voucher_number' => 'PV-1', 'date' => now(), 'payee_name' => 'SAED EST', 'amount' => 20000,
            'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works', 'status' => 'issued',
        ]);
        $subcontract->payments()->create([
            'company_id' => $company->id, 'milestone_id' => $subcontract->milestones->first()->id,
            'payment_voucher_id' => $shahid->id, 'amount' => $shahid->amount, 'paid_at' => $shahid->date,
            'method' => $shahid->method, 'reference' => $shahid->reference,
        ]);

        return [$owner, $subcontract];
    }

    public function test_a_measured_category_computes_amount_from_quantity_times_rate(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $this->actingAs($owner)->post(route('app.subcontracts.report-categories.store', $subcontract), [
            'label' => 'Sub Base', 'display_mode' => 'measured', 'quantity' => 146, 'unit' => 'trip', 'rate' => 500,
        ]);

        $category = SubcontractReportCategory::first();
        $this->assertSame('Sub Base', $category->label);
        $this->assertEqualsWithDelta(73000.00, (float) $category->amount, 0.01);
    }

    public function test_a_measured_category_can_be_a_flat_amount_with_no_quantity(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $this->actingAs($owner)->post(route('app.subcontracts.report-categories.store', $subcontract), [
            'label' => 'Mubashir and Khalid', 'display_mode' => 'measured', 'amount' => 800,
        ]);

        $this->assertEqualsWithDelta(800.00, (float) SubcontractReportCategory::first()->amount, 0.01);
    }

    public function test_updating_quantity_recalculates_the_amount(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();
        $category = $subcontract->reportCategories()->create(['label' => 'Asphalt', 'display_mode' => 'measured', 'quantity' => 646.70, 'unit' => 'ton', 'rate' => 166, 'amount' => 107352.20]);

        // "quantity increase or decrease at the end of project" — a
        // final measurement revises the quantity upward.
        $this->actingAs($owner)->put(route('app.subcontracts.report-categories.update', [$subcontract, $category]), [
            'label' => 'Asphalt', 'display_mode' => 'measured', 'quantity' => 700, 'unit' => 'ton', 'rate' => 166,
        ]);

        $this->assertEqualsWithDelta(116200.00, (float) $category->fresh()->amount, 0.01);
    }

    public function test_an_itemized_category_requires_match_keywords(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $response = $this->actingAs($owner)->post(route('app.subcontracts.report-categories.store', $subcontract), [
            'label' => 'Cash/Transfer', 'display_mode' => 'itemized',
        ]);

        $response->assertSessionHasErrors('match_terms');
        $this->assertSame(0, SubcontractReportCategory::count());
    }

    public function test_the_report_shows_itemized_payments_and_a_measured_summary_line(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();
        $subcontract->reportCategories()->create(['label' => 'Cash/Transfer', 'display_mode' => 'itemized', 'match_terms' => 'shahid']);
        $subcontract->reportCategories()->create(['label' => 'Sub Base', 'display_mode' => 'measured', 'quantity' => 146, 'unit' => 'trip', 'rate' => 500, 'amount' => 73000]);

        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $show = $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract));
        $show->assertOk();
        $show->assertSee('Sub Base');
        $show->assertSee('146');
        $show->assertSee('73,000.00');
    }

    /**
     * A measured category's own figure is shown, never the sum of what
     * it matches — but the real vouchers it covers must still leave the
     * "Other / Unmatched" pool, or that same money would print twice.
     */
    public function test_a_measured_categorys_matched_vouchers_do_not_also_appear_under_other_unmatched(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();
        // makeSubcontractWithPayments() already links one real "Shahid"
        // voucher worth 20,000 — tag it into a measured category by the
        // same keyword, with an independent, unrelated displayed figure.
        $subcontract->reportCategories()->create([
            'label' => 'Sub Base', 'display_mode' => 'measured', 'match_terms' => 'shahid',
            'quantity' => 146, 'unit' => 'trip', 'rate' => 500, 'amount' => 73000,
        ]);

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract));

        $response->assertOk();
        $response->assertDontSee(__('Other / Unmatched'));
    }

    public function test_removing_a_category_deletes_it_without_touching_the_underlying_payments(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();
        $category = $subcontract->reportCategories()->create(['label' => 'Cash/Transfer', 'display_mode' => 'itemized', 'match_terms' => 'shahid']);

        $this->actingAs($owner)->delete(route('app.subcontracts.report-categories.destroy', [$subcontract, $category]));

        $this->assertSame(0, SubcontractReportCategory::count());
        $this->assertSame(1, $subcontract->payments()->count());
    }
}

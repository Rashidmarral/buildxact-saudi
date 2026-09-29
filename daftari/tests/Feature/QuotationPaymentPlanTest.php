<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationPaymentPlanStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A quotation can opt into staged billing (advance + progress payments,
 * as written into QTN-00003's own "Payment Terms" notes: 20/30/30/15/5%)
 * instead of the default single "convert to one invoice" path. Staging is
 * per-quotation, not a global setting — a company keeps using the plain
 * convert flow for quotations that don't need it.
 */
class QuotationPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeAcceptedQuotation(Company $company, Client $client): Quotation
    {
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-00003',
            'type' => 'quotation', 'status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => 'Client Co.',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 625000, 'vat_total' => 93750, 'total' => 718750,
        ]);

        // A real quotation always has line items — needed so
        // convertToInvoice() (which copies quotation items onto the new
        // invoice) produces an invoice with a non-zero total.
        $quotation->items()->create([
            'description' => 'Asphalt paving works', 'quantity' => 1, 'unit_price' => 625000,
            'vat_rate' => 15, 'vat_amount' => 93750, 'line_total' => 718750, 'sort_order' => 0,
        ]);

        return $quotation;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_payment_plan_can_be_saved_with_stages_matching_a_quotations_own_payment_terms(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance payment upon signing', 'percentage' => 20],
                ['description' => 'Upon completion of Subgrade Leveling & Preparation', 'percentage' => 30],
                ['description' => 'Upon completion of Aggregate Base Course', 'percentage' => 30],
                ['description' => 'Upon completion of Asphalt + MC-1', 'percentage' => 15],
                ['description' => 'Final payment upon handover', 'percentage' => 5],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $quotation->refresh();
        $this->assertTrue($quotation->is_staged);
        $this->assertCount(5, $quotation->paymentPlanStages);
        $this->assertEqualsWithDelta(143750, $quotation->paymentPlanStages->first()->amount(), 0.01);
    }

    public function test_stage_percentages_must_sum_to_100(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance', 'percentage' => 20],
                ['description' => 'Rest', 'percentage' => 50],
            ],
        ]);

        $response->assertSessionHasErrors('stages');
        $this->assertFalse($quotation->fresh()->is_staged);
    }

    public function test_generating_a_stage_invoice_creates_a_draft_invoice_for_that_stages_share(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance payment', 'percentage' => 20],
                ['description' => 'Final payment', 'percentage' => 80],
            ],
        ]);

        $advanceStage = $quotation->fresh()->paymentPlanStages->first();

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $advanceStage]));

        $invoice = Invoice::latest('id')->first();
        $response->assertRedirect(route('app.invoices.show', $invoice));

        $this->assertSame($quotation->id, $invoice->quotation_id);
        $this->assertSame('draft', $invoice->status);
        $this->assertEqualsWithDelta(143750, (float) $invoice->total, 0.01);
        $this->assertEqualsWithDelta(125000, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(18750, (float) $invoice->vat_total, 0.01);

        $advanceStage->refresh();
        $this->assertSame($invoice->id, $advanceStage->invoice_id);
        $this->assertNotNull($advanceStage->invoiced_at);

        // Only the invoiced stage is done — the quotation isn't fully
        // converted yet with one stage still outstanding.
        $this->assertSame('accepted', $quotation->fresh()->status);
    }

    public function test_the_quotation_is_marked_converted_once_every_stage_is_invoiced(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance payment', 'percentage' => 40],
                ['description' => 'Final payment', 'percentage' => 60],
            ],
        ]);

        foreach ($quotation->fresh()->paymentPlanStages as $stage) {
            $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]));
        }

        $quotation->refresh();
        $this->assertSame('converted', $quotation->status);
        $this->assertTrue($quotation->isFullyStageInvoiced());
        $this->assertSame(2, Invoice::where('quotation_id', $quotation->id)->count());
    }

    public function test_a_stage_cannot_be_invoiced_twice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [['description' => 'Full', 'percentage' => 100]],
        ]);
        $stage = $quotation->fresh()->paymentPlanStages->first();

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]));
        $this->assertSame(1, Invoice::where('quotation_id', $quotation->id)->count());

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]));

        $response->assertSessionHasErrors('stage');
        $this->assertSame(1, Invoice::where('quotation_id', $quotation->id)->count());
    }

    public function test_a_payment_plan_cannot_be_redefined_once_a_stage_has_been_invoiced(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance', 'percentage' => 20],
                ['description' => 'Rest', 'percentage' => 80],
            ],
        ]);
        $stage = $quotation->fresh()->paymentPlanStages->first();
        $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]));

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [['description' => 'Different plan', 'percentage' => 100]],
        ]);

        $response->assertSessionHasErrors('stages');
        $this->assertCount(2, $quotation->fresh()->paymentPlanStages);
    }

    public function test_the_plain_convert_to_invoice_flow_is_unaffected_for_a_quotation_without_a_payment_plan(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $response = $this->actingAs($owner)->post(route('app.quotations.convert', $quotation));

        $invoice = Invoice::latest('id')->first();
        $response->assertRedirect(route('app.invoices.show', $invoice));
        $this->assertNull($invoice->quotation_id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame($invoice->id, $quotation->fresh()->converted_invoice_id);
    }

    public function test_the_payment_plan_section_renders_on_the_quotation_show_page_in_every_state(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        // Not staged yet — the setup prompt shows.
        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))
            ->assertOk()->assertSee(__('Bill this quotation in stages'));

        $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance payment', 'percentage' => 20],
                ['description' => 'Final payment', 'percentage' => 80],
            ],
        ]);

        // Staged, nothing invoiced yet — the stage list and edit option show.
        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))
            ->assertOk()->assertSee(__('Edit plan'))->assertSee('Advance payment')->assertSee(__('Generate invoice'));

        $stage = $quotation->fresh()->paymentPlanStages->first();
        $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage]));

        // One stage invoiced — the plan is locked, no more "Edit plan".
        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))
            ->assertOk()->assertDontSee(__('Edit plan'))->assertSee(__('View invoice'));
    }

    public function test_company_a_cannot_generate_a_stage_invoice_for_company_bs_quotation(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'B Client']);
        $quotationB = $this->makeAcceptedQuotation($companyB, $clientB);

        $stage = QuotationPaymentPlanStage::create([
            'company_id' => $companyB->id, 'quotation_id' => $quotationB->id, 'sort_order' => 0,
            'description' => 'Advance', 'percentage' => 100,
        ]);
        $quotationB->update(['is_staged' => true]);

        // Company A can't even resolve quotationB via route-model binding
        // (BelongsToCompany scopes it out of their company), so this 404s.
        $response = $this->actingAs($ownerA)->post(route('app.quotations.payment-plan.generate-invoice', [$quotationB, $stage]));
        $response->assertNotFound();
    }

    /**
     * The real-world scenario that prompted this feature: the user
     * plainly converted QTN-00003 (no staging set up yet), recorded the
     * client's 20% advance payment straight on that one invoice, then
     * only afterwards wanted to switch to the 5-stage plan already
     * written into the quotation's own payment terms. The system must
     * credit the existing invoice down to stage 1's own share so it
     * isn't left billing the full amount alongside four new stage
     * invoices.
     */
    public function test_retroactively_staging_an_already_converted_quotation_credits_the_existing_invoice_down_to_stage_one(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.convert', $quotation));
        $invoice = Invoice::latest('id')->first();
        $this->assertFalse($quotation->fresh()->is_staged);

        // The client's 20% advance, recorded on the plain invoice before
        // staging existed for this quotation.
        $payResponse = $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 143750,
            'paid_at' => now()->toDateString(),
            'method' => 'bank_transfer',
        ]);
        $payResponse->assertSessionDoesntHaveErrors();
        $this->assertSame('partially_paid', $invoice->fresh()->status);

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [
                ['description' => 'Advance payment upon signing', 'percentage' => 20],
                ['description' => 'Upon completion of Subgrade Leveling & Preparation', 'percentage' => 30],
                ['description' => 'Upon completion of Aggregate Base Course', 'percentage' => 30],
                ['description' => 'Upon completion of Asphalt + MC-1', 'percentage' => 15],
                ['description' => 'Final payment upon handover', 'percentage' => 5],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $quotation->refresh();
        $this->assertTrue($quotation->is_staged);
        // Four stages remain to be invoiced — still 'accepted', not yet
        // fully converted.
        $this->assertSame('accepted', $quotation->status);
        $this->assertCount(5, $quotation->paymentPlanStages);

        $stage1 = $quotation->paymentPlanStages->first();
        $this->assertSame($invoice->id, $stage1->invoice_id);
        $this->assertNotNull($stage1->invoiced_at);

        // 80% of 718,750 credited off — the invoice now only covers
        // stage 1's own 143,750 share.
        $creditNote = CreditNote::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($creditNote);
        $this->assertEqualsWithDelta(575000, (float) $creditNote->total, 0.01);
        $this->assertSame('issued', $creditNote->status);

        // The 143,750 already paid now exactly covers the invoice's
        // credited-down total — balanceDue() nets to zero, so the
        // invoice is fully paid.
        $invoice->refresh();
        $this->assertEqualsWithDelta(0, $invoice->balanceDue(), 0.01);
        $this->assertSame('paid', $invoice->status);

        // Stages 2-5 are still open and can be invoiced independently.
        $remainingStages = $quotation->paymentPlanStages->skip(1);
        $this->assertCount(4, $remainingStages);
        $this->assertTrue($remainingStages->every(fn ($s) => is_null($s->invoice_id)));

        $stage2 = $remainingStages->first();
        $stageResponse = $this->actingAs($owner)->post(route('app.quotations.payment-plan.generate-invoice', [$quotation, $stage2]));
        $stage2Invoice = Invoice::latest('id')->first();
        $stageResponse->assertRedirect(route('app.invoices.show', $stage2Invoice));
        $this->assertEqualsWithDelta(215625, (float) $stage2Invoice->total, 0.01);
    }

    public function test_retroactive_staging_needs_no_credit_note_when_stage_one_is_the_full_amount(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = $this->makeAcceptedQuotation($company, $client);

        $this->actingAs($owner)->post(route('app.quotations.convert', $quotation));
        $invoice = Invoice::latest('id')->first();

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [['description' => 'Full amount', 'percentage' => 100]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(0, CreditNote::where('invoice_id', $invoice->id)->count());

        $stage = $quotation->fresh()->paymentPlanStages->first();
        $this->assertSame($invoice->id, $stage->invoice_id);
        // A single 100% stage means the quotation is already fully
        // staged-invoiced — mirrors the fresh-staging flow's own
        // "converted once every stage is invoiced" rule.
        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function test_a_draft_quotation_cannot_set_up_a_payment_plan(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-00099',
            'type' => 'quotation', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        $response = $this->actingAs($owner)->post(route('app.quotations.payment-plan.store', $quotation), [
            'stages' => [['description' => 'Full amount', 'percentage' => 100]],
        ]);

        $response->assertNotFound();
    }
}

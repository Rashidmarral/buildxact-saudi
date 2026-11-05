<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit finding NUM-05: invoice/credit-note/debit-note/prepayment/
 * quotation/proforma numbers were a single lifetime counter with no year
 * component and no reset logic. Opt-in via
 * Company::document_numbering_resets_yearly (false by default — see
 * DocumentNumberingRaceTest for proof the default-off path is unchanged).
 */
class YearlyDocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_toggle_defaults_off_and_numbering_is_the_plain_lifetime_counter(): void
    {
        $company = Company::create(['name' => 'Default Numbering Co.', 'slug' => 'default-num-'.uniqid()]);

        $this->assertSame('INV-00001', $company->nextInvoiceNumber());
        $this->assertSame('INV-00002', $company->nextInvoiceNumber());
    }

    public function test_enabling_the_toggle_embeds_the_fiscal_year_and_starts_at_1(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 15));
        $company = Company::create([
            'name' => 'Yearly Numbering Co.', 'slug' => 'yearly-num-'.uniqid(),
            'document_numbering_resets_yearly' => true, 'fiscal_year_start' => 1,
        ]);

        $this->assertSame('INV-2026-00001', $company->nextInvoiceNumber());
        $this->assertSame('INV-2026-00002', $company->nextInvoiceNumber());
    }

    public function test_the_counter_resets_when_the_fiscal_year_rolls_over(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 20));
        $company = Company::create([
            'name' => 'Rollover Co.', 'slug' => 'rollover-'.uniqid(),
            'document_numbering_resets_yearly' => true, 'fiscal_year_start' => 1,
        ]);
        $this->assertSame('INV-2026-00001', $company->nextInvoiceNumber());
        $this->assertSame('INV-2026-00002', $company->nextInvoiceNumber());

        Carbon::setTestNow(Carbon::create(2027, 1, 5));

        $this->assertSame('INV-2027-00001', $company->fresh()->nextInvoiceNumber());
    }

    public function test_a_non_january_fiscal_year_start_is_respected(): void
    {
        // Fiscal year starts in April — a January 2026 date falls inside
        // the fiscal year that began April 2025.
        Carbon::setTestNow(Carbon::create(2026, 1, 10));
        $company = Company::create([
            'name' => 'April FY Co.', 'slug' => 'april-fy-'.uniqid(),
            'document_numbering_resets_yearly' => true, 'fiscal_year_start' => 4,
        ]);

        $this->assertSame('INV-2025-00001', $company->nextInvoiceNumber());

        Carbon::setTestNow(Carbon::create(2026, 4, 1));

        $this->assertSame('INV-2026-00001', $company->fresh()->nextInvoiceNumber());
    }

    public function test_other_document_types_reset_independently_of_each_other(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 1));
        $company = Company::create([
            'name' => 'Multi Doc Co.', 'slug' => 'multi-doc-'.uniqid(),
            'document_numbering_resets_yearly' => true, 'fiscal_year_start' => 1,
        ]);

        $this->assertSame('INV-2026-00001', $company->nextInvoiceNumber());
        $this->assertSame('QTN-2026-00001', $company->fresh()->nextQuotationNumber());
        $this->assertSame('CN-2026-00001', $company->fresh()->nextCreditNoteNumber());
        $this->assertSame('INV-2026-00002', $company->fresh()->nextInvoiceNumber());
        $this->assertSame('QTN-2026-00002', $company->fresh()->nextQuotationNumber());
    }

    public function test_existing_invoices_keep_their_own_historical_numbers_when_the_toggle_is_enabled(): void
    {
        $company = Company::create(['name' => 'Existing Invoices Co.', 'slug' => 'existing-inv-'.uniqid()]);
        $this->assertSame('INV-00001', $company->nextInvoiceNumber());
        $this->assertSame('INV-00002', $company->nextInvoiceNumber());

        // Enabling the toggle never renames INV-00001/INV-00002 — nothing
        // retroactively touches already-issued invoices (see
        // INVOICE_NUMBERING_POLICY.md). It starts a fresh, year-scoped
        // count from here on, which is the correct semantics: "00001"
        // meaning "first of this fiscal year" would be false if it
        // silently continued the old lifetime count instead.
        $company->update(['document_numbering_resets_yearly' => true]);

        $this->assertSame('INV-'.now()->year.'-00001', $company->fresh()->nextInvoiceNumber());
    }

    public function test_the_toggle_can_be_turned_on_from_settings(): void
    {
        $company = Company::create(['name' => 'Settings Toggle Co.', 'slug' => 'settings-toggle-'.uniqid(), 'primary_customer_type' => 'mixed', 'negative_number_format' => 'minus']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), [
            'name' => $company->name,
            'invoice_prefix' => $company->invoice_prefix,
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
            'document_numbering_resets_yearly' => '1',
        ]);
        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        $this->assertTrue((bool) $company->fresh()->document_numbering_resets_yearly);
    }
}

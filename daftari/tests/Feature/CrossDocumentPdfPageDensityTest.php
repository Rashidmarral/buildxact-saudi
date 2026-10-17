<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The same mPDF bug PdfPageDensityTest covers for invoices — a bare
 * trailing <div class="footer-note"> right after a closing </table> at
 * the very end of the document's HTML flow gets its page-break space
 * overestimated, forcing a spurious blank extra page — turned out to
 * also exist, unfixed, in five other PDF templates outside the shared
 * documents.print.pdf invoice/quotation file: purchase-order-pdf.blade.php,
 * client-statement-pdf.blade.php, saas-receipt.blade.php, the
 * partials/report/footer.blade.php partial shared by 15 report/ledger
 * PDF document types (owner-reimbursements, cash-custody,
 * kafeel-payments, and every report under user/reports/pdf/*), and
 * vat-return-period.blade.php (which had its own extra bare div on top
 * of including that same risky partial — two stacked instances of the
 * bug in one document). The quotation_offer branch of pdf.blade.php
 * itself was also found still using the old unsafe pattern — missed
 * when every other layout branch in that file was converted during the
 * original invoice-density fix.
 *
 * All six were converted to the same safe <table><tr><td
 * class="footer-note">...</td></tr></table> wrapper already proven for
 * invoices. These tests build a realistic, moderately dense document of
 * each affected type and assert it renders as a single PDF page, so a
 * future regression of this exact bug (on any of these document types)
 * fails a test instead of silently shipping a blank second page again.
 */
class CrossDocumentPdfPageDensityTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function countPdfPages(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $matches);

        return count($matches[0]);
    }

    public function test_a_quotation_offer_with_several_items_and_terms_fits_on_one_page(): void
    {
        $logoPath = 'logos/qo-density-'.uniqid().'.png';
        $stampPath = 'stamps/qo-density-'.uniqid().'.png';
        Storage::disk('public')->put($logoPath, $this->tinyPngBytes());
        Storage::disk('public')->put($stampPath, $this->tinyPngBytes());

        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company', 'name_ar' => 'شركة داينمك كور للمقاولات',
            'slug' => 'qo-density-'.uniqid(), 'cr_number' => '7053180563', 'phone' => '0568582270',
            'logo_path' => $logoPath, 'stamp_path' => $stampPath,
        ]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'all',
            'layout' => 'quotation_offer', 'language_mode' => 'bilingual', 'is_default' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment', 'name_ar' => 'مؤسسة سادة الجيل']);

        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QTN-00006', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'currency' => $company->currency ?? 'SAR',
            'notes' => "20% advance on signing.\n30% on completion of Subbase.\n50% on final handover.",
            'subtotal' => 0, 'vat_total' => 0, 'total' => 0,
        ]);

        for ($i = 0; $i < 6; $i++) {
            QuotationItem::create([
                'quotation_id' => $quotation->id, 'description' => "Line item {$i}",
                'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150,
            ]);
        }
        $quotation->update(['subtotal' => 6000, 'vat_total' => 900, 'total' => 6900]);

        $response = $this->actingAs($owner)->get(route('app.quotations.pdf', $quotation));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()), 'quotation_offer spilled a 6-item quotation onto a second page.');
    }

    public function test_a_purchase_order_with_several_items_and_notes_fits_on_one_page(): void
    {
        $stampPath = 'stamps/po-density-'.uniqid().'.png';
        Storage::disk('public')->put($stampPath, $this->tinyPngBytes());

        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'po-density-'.uniqid(), 'stamp_path' => $stampPath]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'name_ar' => 'مؤسسة سائد']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Al-Jumum Square Road Works', 'status' => 'active', 'created_by' => $owner->id]);

        $order = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'po_number' => 'PO-0001', 'status' => 'approved', 'order_date' => now()->toDateString(),
            'notes' => "20% advance on signing.\n30% on completion of Subbase.\n50% on final handover.",
            'subtotal' => 0, 'vat_total' => 0, 'total' => 0,
        ]);

        for ($i = 0; $i < 6; $i++) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id, 'description' => "Material line {$i}",
                'quantity' => 1, 'unit_price' => 95000, 'vat_rate' => 15, 'vat_amount' => 14250, 'line_total' => 109250,
            ]);
        }
        $order->update(['subtotal' => 570000, 'vat_total' => 85500, 'total' => 655500]);

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()), 'Purchase Order PDF spilled a 6-item order onto a second page.');
    }

    public function test_a_short_client_statement_fits_on_one_page(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_client_statements' => true,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة داينامك كور', 'slug' => 'cs-density-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction', 'vat_number' => '399999999900003']);

        $response = $this->actingAs($owner)->get(route('app.client-statements.pdf', $client));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()), 'Client statement PDF spilled an (otherwise near-empty) statement onto a second page.');
    }

    public function test_a_vat_return_period_with_notes_fits_on_one_page(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة داينامك كور', 'slug' => 'vrp-density-'.uniqid(), 'vat_number' => '300012345600003']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $period = $company->vatReturnPeriods()->create([
            'created_by' => $owner->id, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'output_tax' => 15000, 'input_tax_purchases' => 6000, 'expense_tax' => 1500, 'net_recoverable_input_tax' => 7500,
            'credit_brought_forward' => 0, 'amount_payable' => 7500, 'credit_carried_forward' => 0,
            'notes' => "Filed on ZATCA portal 2026-04-10.\nIncludes one reversed input-VAT claim from Q4 corrected this period.",
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.pdf', $period));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()), 'VAT return period PDF spilled onto a second page (this file has two stacked instances of the trailing-footer-div bug).');
    }

    public function test_a_subscription_receipt_fits_on_one_page(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة داينامك كور', 'slug' => 'receipt-density-'.uniqid(), 'vat_number' => '300012345600003']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $payment = Payment::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'amount' => 1000, 'currency' => 'SAR',
            'status' => 'paid', 'method' => 'card', 'reference' => 'CH-TEST-0001', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('app.billing.receipt', $payment));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()), 'Subscription receipt PDF spilled onto a second page.');
    }
}

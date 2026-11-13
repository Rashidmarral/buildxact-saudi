<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real crash reported in production: "Undefined array key 'payment_method'"
 * at body.blade.php:941 viewing a debit note with "Distribution Classic"
 * active. Only Invoice's own $doc array actually sets 'payment_method' —
 * every other document type (quotations, bills, purchase orders, credit
 * notes, debit notes, purchase returns) never includes the key at all.
 * The distribution_classic layout's info-grid row read it with
 * $doc['payment_method'] ?: ... — the ?: short-circuit operator still has
 * to evaluate the left side first, so accessing a genuinely-undefined
 * array key threw before the fallback ever ran. Fixed to
 * ($doc['payment_method'] ?? null) ?: ...; this test covers every
 * document type that lacks the key, so the same class of bug can't slip
 * back in through one of them.
 */
class DistributionClassicMissingPaymentMethodKeyTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'No Payment Method Co.', 'slug' => 'no-pm-'.uniqid()]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Distribution Classic', 'document_type' => 'all',
            'layout' => 'distribution_classic', 'is_default' => true,
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_debit_note_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 10, 'vat_total' => 1.5, 'total' => 11.5,
        ]);
        $debitNote->items()->create(['description' => 'Extra charge', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 1.5, 'line_total' => 11.5]);

        $this->actingAs($owner)->get(route('app.debit-notes.show', $debitNote))->assertOk();
        $this->actingAs($owner)->get(route('app.debit-notes.pdf', $debitNote))->assertOk();
    }

    public function test_a_credit_note_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 10, 'vat_total' => 1.5, 'total' => 11.5,
        ]);
        $creditNote->items()->create(['description' => 'Returned item', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 1.5, 'line_total' => 11.5]);

        $this->actingAs($owner)->get(route('app.credit-notes.show', $creditNote))->assertOk();
        $this->actingAs($owner)->get(route('app.credit-notes.pdf', $creditNote))->assertOk();
    }

    public function test_a_quotation_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-1',
            'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $quotation->items()->create(['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))->assertOk();
        $this->actingAs($owner)->get(route('app.quotations.pdf', $quotation))->assertOk();
    }

    public function test_a_bill_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $bill->items()->create(['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->actingAs($owner)->get(route('app.bills.show', $bill))->assertOk();
        $this->actingAs($owner)->get(route('app.bills.pdf', $bill))->assertOk();
    }

    public function test_a_purchase_order_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $po = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-1',
            'order_date' => now(), 'status' => 'draft', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $po->items()->create(['description' => 'Materials', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->actingAs($owner)->get(route('app.purchase-orders.show', $po))->assertOk();
        $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $po))->assertOk();
    }

    public function test_a_purchase_return_renders_without_a_payment_method_key(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $purchaseReturn = PurchaseReturn::create([
            'company_id' => $company->id, 'bill_id' => $bill->id, 'supplier_id' => $supplier->id,
            'return_number' => 'PR-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 10, 'vat_total' => 1.5, 'total' => 11.5,
        ]);
        $purchaseReturn->items()->create(['description' => 'Returned item', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 1.5, 'line_total' => 11.5]);

        $this->actingAs($owner)->get(route('app.purchase-returns.show', $purchaseReturn))->assertOk();
        $this->actingAs($owner)->get(route('app.purchase-returns.pdf', $purchaseReturn))->assertOk();
    }
}

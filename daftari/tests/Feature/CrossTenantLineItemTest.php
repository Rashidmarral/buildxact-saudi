<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Commercial audit finding A6: bill_items/invoice_items carry no
 * company_id of their own, so CreditNoteController::store() and
 * PurchaseReturnController::store() used to look up the source line via
 * an unscoped InvoiceItem::find()/BillItem::find() — a company could
 * submit its own invoice_id/bill_id (which is company-scoped and 404s if
 * foreign) alongside another company's sequential invoice_item_id/
 * bill_item_id and successfully pull that other company's line (only
 * unit_id actually leaked, since amounts/description are attacker-
 * supplied either way, but the read itself crossed the tenant boundary).
 * Both now resolve the source line through the already-validated,
 * company-owned parent instead.
 */
class CrossTenantLineItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithOwner(string $name): array
    {
        $company = Company::create(['name' => $name, 'slug' => Str::slug($name).'-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_credit_note_does_not_leak_another_companys_invoice_item_unit(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner('Company A');
        [$companyB, $ownerB] = $this->makeCompanyWithOwner('Company B');

        $unitA = Unit::create(['company_id' => $companyA->id, 'name' => 'Ton', 'code' => 'TNE']);
        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Client A']);
        $invoiceA = Invoice::create([
            'company_id' => $companyA->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $itemA = $invoiceA->items()->create(['unit_id' => $unitA->id, 'description' => 'Secret Item A', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);
        $invoiceA->recalculateTotals();
        $invoiceA->save();

        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        $invoiceB = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoiceB->items()->create(['description' => 'Item B', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15, 'vat_amount' => 7.5, 'line_total' => 57.5]);
        $invoiceB->recalculateTotals();
        $invoiceB->save();

        $response = $this->actingAs($ownerB)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoiceB->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'invoice_item_id' => $itemA->id,
                'description' => 'Attacker-controlled description',
                'quantity' => 1,
                'unit_price' => 50,
                'vat_rate' => 15,
            ]],
        ]);

        $response->assertRedirect();
        $creditNoteItem = CreditNoteItem::where('invoice_item_id', $itemA->id)->first();
        $this->assertNotNull($creditNoteItem);
        $this->assertNull($creditNoteItem->unit_id, "Company B's credit note must not have picked up Company A's unit_id.");
    }

    public function test_purchase_return_does_not_leak_another_companys_bill_item_unit(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner('Company A');
        [$companyB, $ownerB] = $this->makeCompanyWithOwner('Company B');

        $unitA = Unit::create(['company_id' => $companyA->id, 'name' => 'Ton', 'code' => 'TNE']);
        $supplierA = Supplier::create(['company_id' => $companyA->id, 'name' => 'Supplier A']);
        $billA = Bill::create([
            'company_id' => $companyA->id, 'supplier_id' => $supplierA->id, 'bill_number' => 'BILL-A-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $itemA = $billA->items()->create(['unit_id' => $unitA->id, 'description' => 'Secret Purchase A', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);
        $billA->recalculateTotals();
        $billA->save();

        $supplierB = Supplier::create(['company_id' => $companyB->id, 'name' => 'Supplier B']);
        $billB = Bill::create([
            'company_id' => $companyB->id, 'supplier_id' => $supplierB->id, 'bill_number' => 'BILL-B-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $billB->items()->create(['description' => 'Purchase B', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15, 'vat_amount' => 7.5, 'line_total' => 57.5]);
        $billB->recalculateTotals();
        $billB->save();

        $response = $this->actingAs($ownerB)->post(route('app.purchase-returns.store'), [
            'bill_id' => $billB->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'bill_item_id' => $itemA->id,
                'description' => 'Attacker-controlled description',
                'quantity' => 1,
                'unit_price' => 50,
                'vat_rate' => 15,
            ]],
        ]);

        $response->assertRedirect();
        $returnItem = PurchaseReturnItem::where('bill_item_id', $itemA->id)->first();
        $this->assertNotNull($returnItem);
        $this->assertNull($returnItem->unit_id, "Company B's purchase return must not have picked up Company A's unit_id.");
    }
}

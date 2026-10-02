<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding M-23: CreditNoteController/DebitNoteController's
 * invoice_id and PurchaseReturnController's bill_id used a bare, unscoped
 * exists rule — "safe" only because the controller re-fetches the model
 * through Invoice::findOrFail()/Bill::findOrFail() right after, which 404s
 * on a foreign id instead of failing validation cleanly. Scoping the rule
 * itself turns that into a normal 422 validation error.
 */
class CreditDebitNoteAndPurchaseReturnRejectCrossCompanyParentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithOwner(): array
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_credit_note_cannot_be_issued_against_another_companys_invoice(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner();
        [$companyB] = $this->makeCompanyWithOwner();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        $invoiceB = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoiceB->items()->create(['description' => 'Item B', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15, 'vat_amount' => 7.5, 'line_total' => 57.5]);
        $invoiceB->recalculateTotals();
        $invoiceB->save();

        $response = $this->actingAs($ownerA)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoiceB->id,
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertDatabaseMissing('credit_notes', ['invoice_id' => $invoiceB->id]);
    }

    public function test_a_debit_note_cannot_be_issued_against_another_companys_invoice(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner();
        [$companyB] = $this->makeCompanyWithOwner();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        $invoiceB = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($ownerA)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoiceB->id,
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertDatabaseMissing('debit_notes', ['invoice_id' => $invoiceB->id]);
    }

    public function test_a_purchase_return_cannot_be_issued_against_another_companys_bill(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner();
        [$companyB] = $this->makeCompanyWithOwner();
        $supplierB = Supplier::create(['company_id' => $companyB->id, 'name' => 'Supplier B']);
        $billB = Bill::create([
            'company_id' => $companyB->id, 'supplier_id' => $supplierB->id, 'bill_number' => 'BILL-B-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $billB->items()->create(['description' => 'Purchase B', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15, 'vat_amount' => 7.5, 'line_total' => 57.5]);
        $billB->recalculateTotals();
        $billB->save();

        $response = $this->actingAs($ownerA)->post(route('app.purchase-returns.store'), [
            'bill_id' => $billB->id,
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('bill_id');
        $this->assertDatabaseMissing('purchase_returns', ['bill_id' => $billB->id]);
    }
}

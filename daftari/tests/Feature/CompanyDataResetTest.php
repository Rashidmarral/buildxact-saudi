<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Admin\CompanyDataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to clean each and every transaction records permanently except
 * the invoices with safe way" — followed by "make a something more
 * advance on admin side who delete or wipe out transaction all records he
 * want for a specific company ... new entries start from 1 in database as
 * well frontend". CompanyDataResetService/CompanyDataResetController is
 * that admin tool: a category-by-category wipe of one company's
 * transaction records, scoped so Invoices (and their items) are never
 * offered and never touched, with each wiped category's document-number
 * counter rewound to 1 and its orphaned journal entries removed.
 */
class CompanyDataResetTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null, 'status' => 'active']);
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Reset Target Co.', 'slug' => 'reset-target-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeAccount(Company $company, string $code = '1100'): Account
    {
        return Account::create([
            'company_id' => $company->id, 'code' => $code, 'name' => 'Test Account',
            'type' => 'asset', 'normal_balance' => 'debit',
        ]);
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Kept Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'type' => 'standard', 'status' => 'issued', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Kept line item',
            'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'line_total' => 100,
        ]);

        return $invoice;
    }

    private function makeBill(Company $company): Bill
    {
        $supplier = Supplier::create(['company_id' => $company->id, 'type' => 'company', 'name' => 'Test Supplier']);

        return Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'status' => 'issued', 'bill_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
    }

    private function makeCreditNote(Company $company, Invoice $invoice): CreditNote
    {
        return CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-00001', 'issue_date' => now()->toDateString(), 'status' => 'issued',
        ]);
    }

    private function makeJournalEntry(Company $company, Account $account, string $sourceType, int $sourceId): JournalEntry
    {
        $entry = JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-'.uniqid(), 'entry_date' => now()->toDateString(),
            'source_type' => $sourceType, 'source_id' => $sourceId, 'description' => 'Test entry',
        ]);
        $entry->lines()->create(['account_id' => $account->id, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $account->id, 'debit' => 0, 'credit' => 100]);

        return $entry;
    }

    // ---------------------------------------------------------------
    // Access control
    // ---------------------------------------------------------------

    public function test_a_non_admin_cannot_reach_the_preview_or_execute_routes(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($owner)->get(route('admin.companies.data-reset.show', $company))->assertForbidden();
        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), ['categories' => ['bills'], 'confirm_name' => $company->name])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Preview
    // ---------------------------------------------------------------

    public function test_preview_counts_match_what_actually_exists_per_category(): void
    {
        $company = $this->makeCompany();
        $this->makeBill($company);
        $this->makeBill($company);

        $counts = app(CompanyDataResetService::class)->preview($company);

        $this->assertSame(2, $counts['bills']);
        $this->assertSame(0, $counts['purchase_orders']);
    }

    // ---------------------------------------------------------------
    // Execute — the core mechanism
    // ---------------------------------------------------------------

    public function test_executing_wipes_only_the_selected_category_and_leaves_others_untouched(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $this->makeBill($company);
        $po = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => Supplier::create(['company_id' => $company->id, 'type' => 'company', 'name' => 'S'])->id,
            'po_number' => 'PO-00001', 'status' => 'issued', 'order_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $this->assertSame(0, Bill::where('company_id', $company->id)->count());
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    public function test_invoices_and_their_items_are_never_touched_regardless_of_selection(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $this->makeBill($company);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => array_keys(app(CompanyDataResetService::class)->registry()),
                'confirm_name' => $company->name,
            ]);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id]);
    }

    public function test_journal_entries_for_a_wiped_category_are_removed_but_kept_invoices_entries_survive(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);
        $bill = $this->makeBill($company);

        $invoiceEntry = $this->makeJournalEntry($company, $account, 'invoice', $invoice->id);
        $billEntry = $this->makeJournalEntry($company, $account, 'bill', $bill->id);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ]);

        $this->assertDatabaseMissing('journal_entries', ['id' => $billEntry->id]);
        $this->assertDatabaseMissing('journal_entry_lines', ['journal_entry_id' => $billEntry->id]);
        $this->assertDatabaseHas('journal_entries', ['id' => $invoiceEntry->id]);
    }

    public function test_numbering_restarts_at_one_only_for_wiped_categories(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $this->makeBill($company);
        $company->nextBillNumber();
        $company->nextBillNumber();
        $company->nextPoNumber();
        $company->refresh();
        $this->assertGreaterThan(1, $company->next_bill_number);
        $poNumberBefore = $company->next_po_number;

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ]);

        $company->refresh();
        $this->assertSame(1, $company->next_bill_number);
        $this->assertSame($poNumberBefore, $company->next_po_number);
    }

    public function test_an_attachment_on_a_wiped_document_is_removed_but_a_kept_invoice_attachment_survives(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $bill = $this->makeBill($company);

        $billAttachment = Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Bill::class, 'attachable_id' => $bill->id,
            'original_name' => 'bill.pdf', 'path' => 'attachments/bill-test.pdf', 'size' => 10, 'mime_type' => 'application/pdf',
        ]);
        $invoiceAttachment = Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Invoice::class, 'attachable_id' => $invoice->id,
            'original_name' => 'invoice.pdf', 'path' => 'attachments/invoice-test.pdf', 'size' => 10, 'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ]);

        $this->assertDatabaseMissing('attachments', ['id' => $billAttachment->id]);
        $this->assertDatabaseHas('attachments', ['id' => $invoiceAttachment->id]);
    }

    public function test_execution_is_refused_without_the_exact_company_name_typed_and_nothing_is_deleted(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $this->makeBill($company);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => 'the wrong name',
            ])
            ->assertSessionHasErrors('confirm_name');

        $this->assertSame(1, Bill::where('company_id', $company->id)->count());
    }

    public function test_execution_is_blocked_entirely_for_a_company_with_zatca_activity(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany(['zatca_onboarding_status' => 'production']);
        $this->makeBill($company);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ])
            ->assertSessionHasErrors('company');

        $this->assertSame(1, Bill::where('company_id', $company->id)->count());
    }

    public function test_the_preview_page_still_renders_for_a_zatca_active_company_but_execution_stays_blocked(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany(['zatca_onboarding_status' => 'production']);
        $this->makeBill($company);

        $this->actingAs($admin)->get(route('admin.companies.data-reset.show', $company))
            ->assertOk()
            ->assertSee('ZATCA activity on file');
    }

    public function test_an_audit_log_entry_is_recorded(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $this->makeBill($company);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['bills'],
                'confirm_name' => $company->name,
            ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'company.data_reset', 'admin_user_id' => $admin->id]);
    }

    // ---------------------------------------------------------------
    // Master data — clients/suppliers/employees/users
    // ---------------------------------------------------------------

    /**
     * "test client and supplier linked with quotation invoice or bill
     * that will be deleted also" — a Client still referenced by a KEPT
     * Invoice must still be deletable (the invoice falls back to its own
     * frozen party_snapshot instead of blocking the delete), while an
     * Invoice tied to a Client that gets deleted alongside it (both
     * categories selected) is of course gone too.
     */
    public function test_a_client_can_be_deleted_even_though_a_kept_invoice_still_references_it(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $invoice->update(['status' => 'sent']);
        $invoice->capturePartySnapshot();
        $clientId = $invoice->client_id;
        $clientName = $invoice->client->name;

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['clients'],
                'confirm_name' => $company->name,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
        $invoice->refresh();
        $this->assertNull($invoice->client_id);
        $this->assertSame($clientName, $invoice->displayClientName());
    }

    public function test_suppliers_employees_and_non_owner_users_are_wiped_while_the_owner_survives(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'staff', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'type' => 'company', 'name' => 'Old Supplier']);
        $employee = \App\Models\Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-001', 'full_name' => 'Old Employee', 'hire_date' => now()->toDateString(), 'status' => 'active']);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.data-reset.execute', $company), [
                'categories' => ['suppliers', 'employees', 'users'],
                'confirm_name' => $company->name,
            ]);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}

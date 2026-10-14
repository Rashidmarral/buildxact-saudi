<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ApprovalChainStep;
use App\Models\ApprovalProgress;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extends the multi-tier ApprovalChainStep/ApprovalChainService machinery
 * (previously only wired up for purchase_order/expense — see
 * ApprovalChainTest) to cover two more gaps the user asked to have
 * verified against the ZATCA spec work: sending an invoice through a
 * configured chain (mirroring PurchaseOrderController's pattern exactly),
 * and a one-shot "most senior configured role must sign off" gate on
 * cancelling an already-sent invoice or voiding a credit note — actions
 * that previously needed only the base "invoices" permission no matter
 * how large the document, with no account for a company that has defined
 * an actual approval seniority structure. Deliberately not a new
 * pending-cancellation lifecycle: cancelling/voiding stays a single
 * synchronous action, just gated by who may perform it.
 */
class InvoiceApprovalChainAndSeniorVoidGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Chain Invoicing Co.', 'slug' => 'cic-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeRoleHolder(Company $company, Role $role, array $permissions): User
    {
        $modulePermRole = Role::create(['company_id' => $company->id, 'name' => 'Module-'.$role->id, 'slug' => 'module-'.uniqid(), 'permissions' => $permissions]);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $member->roles()->attach([$role->id, $modulePermRole->id]);

        return $member;
    }

    private function makeChainRoles(Company $company): array
    {
        return [
            'manager' => Role::create(['company_id' => $company->id, 'name' => 'Manager', 'slug' => 'manager-'.uniqid(), 'permissions' => []]),
            'finance' => Role::create(['company_id' => $company->id, 'name' => 'Finance', 'slug' => 'finance-'.uniqid(), 'permissions' => []]),
        ];
    }

    private function makeInvoiceChain(Company $company, array $roles): void
    {
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'invoice', 'step_number' => 1, 'role_id' => $roles['manager']->id, 'min_amount' => 500]);
        ApprovalChainStep::create(['company_id' => $company->id, 'document_type' => 'invoice', 'step_number' => 2, 'role_id' => $roles['finance']->id, 'min_amount' => 500]);
    }

    private function makeClient(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Big Client LLC']);
    }

    private function invoicePayload(Client $client, float $unitPrice = 1000): array
    {
        return [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'send_immediately' => '1',
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $unitPrice, 'vat_rate' => 15],
            ],
        ];
    }

    public function test_an_invoice_crossing_the_chain_tier_needs_manager_then_finance_in_order(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $manager = $this->makeRoleHolder($company, $roles['manager'], ['invoices']);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['invoices']);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client));
        $invoice = Invoice::latest('id')->first();
        $this->assertSame('pending_approval', $invoice->status);
        $this->assertSame(2, ApprovalProgress::where('approvable_id', $invoice->id)->where('approvable_type', Invoice::class)->count());

        $this->actingAs($finance)->post(route('app.invoices.approve', $invoice))->assertForbidden();

        $this->actingAs($manager)->post(route('app.invoices.approve', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('pending_approval', $invoice->fresh()->status);

        $this->actingAs($finance)->post(route('app.invoices.approve', $invoice))->assertSessionDoesntHaveErrors();
        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame($finance->id, $invoice->approved_by);
    }

    public function test_an_invoice_below_every_chain_tier_sends_instantly(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client, unitPrice: 100));

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame(0, ApprovalProgress::where('approvable_id', $invoice->id)->count());
    }

    public function test_rejecting_at_the_manager_step_returns_the_invoice_to_draft(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $manager = $this->makeRoleHolder($company, $roles['manager'], ['invoices']);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client));
        $invoice = Invoice::latest('id')->first();

        $this->actingAs($manager)->post(route('app.invoices.reject', $invoice), ['rejection_reason' => 'Wrong amount'])
            ->assertSessionDoesntHaveErrors();

        $invoice->refresh();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('Wrong amount', $invoice->rejection_reason);

        $progress = ApprovalProgress::where('approvable_id', $invoice->id)->orderBy('step_number')->get();
        $this->assertSame('rejected', $progress[0]->status);
        $this->assertSame('skipped', $progress[1]->status);
    }

    private function sentInvoice(Company $company, User $owner, Client $client): Invoice
    {
        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client, unitPrice: 100));

        return Invoice::latest('id')->first();
    }

    public function test_cancelling_an_invoice_is_unrestricted_when_no_chain_is_configured(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $invoice = $this->sentInvoice($company, $owner, $client);

        $staff = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $staff->roles()->attach(Role::create(['company_id' => $company->id, 'name' => 'Staff', 'slug' => 'staff-'.uniqid(), 'permissions' => ['invoices']])->id);

        $this->actingAs($staff)->post(route('app.invoices.cancel', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_cancelling_an_invoice_requires_the_most_senior_chain_role_once_one_is_configured(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $invoice = $this->sentInvoice($company, $owner, $client);

        $manager = $this->makeRoleHolder($company, $roles['manager'], ['invoices']);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['invoices']);

        // Manager holds a configured step, but not the most senior one (Finance is step 2).
        $this->actingAs($manager)->post(route('app.invoices.cancel', $invoice))->assertSessionHasErrors('invoice');
        $this->assertSame('sent', $invoice->fresh()->status);

        $this->actingAs($finance)->post(route('app.invoices.cancel', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_the_owner_can_always_cancel_an_invoice_regardless_of_the_configured_chain(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $invoice = $this->sentInvoice($company, $owner, $client);

        $this->actingAs($owner)->post(route('app.invoices.cancel', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    private function issuedCreditNote(Company $company, Invoice $invoice, Client $client): CreditNote
    {
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-'.uniqid(), 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
        CreditNoteItem::create(['credit_note_id' => $creditNote->id, 'description' => 'Refund', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 100]);

        return $creditNote;
    }

    public function test_voiding_a_credit_note_requires_the_most_senior_chain_role_once_one_is_configured(): void
    {
        $company = $this->makeCompany();
        $roles = $this->makeChainRoles($company);
        $this->makeInvoiceChain($company, $roles);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $invoice = $this->sentInvoice($company, $owner, $client);
        $creditNote = $this->issuedCreditNote($company, $invoice, $client);

        $manager = $this->makeRoleHolder($company, $roles['manager'], ['invoices']);
        $finance = $this->makeRoleHolder($company, $roles['finance'], ['invoices']);

        $this->actingAs($manager)->post(route('app.credit-notes.void', $creditNote))->assertSessionHasErrors('credit_note');
        $this->assertSame('issued', $creditNote->fresh()->status);

        $this->actingAs($finance)->post(route('app.credit-notes.void', $creditNote))->assertSessionDoesntHaveErrors();
        $this->assertSame('void', $creditNote->fresh()->status);
    }

    public function test_voiding_a_credit_note_is_unrestricted_when_no_chain_is_configured(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $invoice = $this->sentInvoice($company, $owner, $client);
        $creditNote = $this->issuedCreditNote($company, $invoice, $client);

        $staff = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $staff->roles()->attach(Role::create(['company_id' => $company->id, 'name' => 'Staff', 'slug' => 'staff-'.uniqid(), 'permissions' => ['invoices']])->id);

        $this->actingAs($staff)->post(route('app.credit-notes.void', $creditNote))->assertSessionDoesntHaveErrors();
        $this->assertSame('void', $creditNote->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * Audit finding: invoices and quotations could be sent/issued (and posted
 * to the ledger, in the invoice's case) by whoever created them, no matter
 * the amount — unlike purchase orders and expenses, which already had an
 * approval-threshold gate. This extends the same pattern: a company can
 * set an invoice/quotation approval threshold, above which sending/issuing
 * parks the document in pending_approval until a user holding the
 * "approvals" permission signs off.
 */
class InvoiceQuotationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(?float $invoiceThreshold = null, ?float $quotationThreshold = null): Company
    {
        $company = Company::create([
            'name' => 'Approval Co.',
            'slug' => 'approval-'.uniqid(),
            'invoice_approval_threshold' => $invoiceThreshold,
            'quotation_approval_threshold' => $quotationThreshold,
        ]);

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeApprover(Company $company): User
    {
        // An approver must also hold the module's own permission to reach
        // the approve/reject routes at all — they sit behind the same
        // permission:invoices / permission:quotations middleware as every
        // other invoice/quotation action, same as the seeded "accountant"
        // role preset which carries both.
        $role = Role::create(['company_id' => $company->id, 'name' => 'Approver', 'slug' => 'approver-'.uniqid(), 'permissions' => ['approvals', 'invoices', 'quotations']]);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $member->roles()->attach($role->id);

        return $member;
    }

    private function makeNonApprover(Company $company): User
    {
        $role = Role::create(['company_id' => $company->id, 'name' => 'Staff', 'slug' => 'staff-'.uniqid(), 'permissions' => ['dashboard']]);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $member->roles()->attach($role->id);

        return $member;
    }

    private function makeClient(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Big Client LLC']);
    }

    private function invoicePayload(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'send_immediately' => '1',
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15],
            ],
        ];
    }

    private function quotationPayload(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'send_immediately' => '1',
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15],
            ],
        ];
    }

    public function test_invoice_above_threshold_is_parked_pending_approval_instead_of_sent(): void
    {
        $company = $this->makeCompany(invoiceThreshold: 500);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client))
            ->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('pending_approval', $invoice->status);
        $this->assertFalse($invoice->stock_deducted);
    }

    public function test_invoice_below_threshold_sends_immediately_without_approval(): void
    {
        $company = $this->makeCompany(invoiceThreshold: 5000);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client))
            ->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('sent', $invoice->status);
    }

    public function test_approving_a_pending_invoice_sends_it_and_notifies_stay_consistent(): void
    {
        $company = $this->makeCompany(invoiceThreshold: 500);
        $owner = $this->makeOwner($company);
        $approver = $this->makeApprover($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client));
        $invoice = Invoice::latest('id')->first();
        $this->assertSame('pending_approval', $invoice->status);

        $this->actingAs($approver)->post(route('app.invoices.approve', $invoice))
            ->assertSessionDoesntHaveErrors();

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame($approver->id, $invoice->approved_by);
        $this->assertNotNull($invoice->approved_at);
    }

    public function test_rejecting_a_pending_invoice_returns_it_to_draft_with_reason_and_notifies_creator(): void
    {
        $company = $this->makeCompany(invoiceThreshold: 500);
        $owner = $this->makeOwner($company);
        $approver = $this->makeApprover($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client));
        $invoice = Invoice::latest('id')->first();

        $this->actingAs($approver)->post(route('app.invoices.reject', $invoice), ['rejection_reason' => 'Numbers look off'])
            ->assertSessionDoesntHaveErrors();

        $invoice->refresh();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('Numbers look off', $invoice->rejection_reason);
        // Owner is also notified when the invoice is first submitted for
        // approval (owner always holds the "approvals" permission), plus
        // again here on rejection — so at least the rejection notice.
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $owner->id)->where('data->title', __('Invoice rejected'))->exists());
    }

    public function test_a_non_approver_cannot_approve_or_reject_a_pending_invoice(): void
    {
        $company = $this->makeCompany(invoiceThreshold: 500);
        $owner = $this->makeOwner($company);
        $staff = $this->makeNonApprover($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->invoicePayload($client));
        $invoice = Invoice::latest('id')->first();

        $this->actingAs($staff)->post(route('app.invoices.approve', $invoice))->assertForbidden();
        $this->actingAs($staff)->post(route('app.invoices.reject', $invoice))->assertForbidden();

        $this->assertSame('pending_approval', $invoice->fresh()->status);
    }

    public function test_quotation_above_threshold_is_parked_pending_approval_instead_of_issued(): void
    {
        $company = $this->makeCompany(quotationThreshold: 500);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), $this->quotationPayload($client))
            ->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertSame('pending_approval', $quotation->status);
    }

    public function test_approving_a_pending_quotations_issuance_marks_it_issued(): void
    {
        $company = $this->makeCompany(quotationThreshold: 500);
        $owner = $this->makeOwner($company);
        $approver = $this->makeApprover($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), $this->quotationPayload($client));
        $quotation = Quotation::latest('id')->first();

        $this->actingAs($approver)->post(route('app.quotations.approve-issuance', $quotation))
            ->assertSessionDoesntHaveErrors();

        $quotation->refresh();
        $this->assertSame('issued', $quotation->status);
        $this->assertSame($approver->id, $quotation->approved_by);
    }

    public function test_rejecting_a_pending_quotations_issuance_returns_it_to_draft_with_reason_and_notifies_creator(): void
    {
        $company = $this->makeCompany(quotationThreshold: 500);
        $owner = $this->makeOwner($company);
        $approver = $this->makeApprover($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), $this->quotationPayload($client));
        $quotation = Quotation::latest('id')->first();

        $this->actingAs($approver)->post(route('app.quotations.reject-issuance', $quotation), ['approval_rejection_reason' => 'Discount too high'])
            ->assertSessionDoesntHaveErrors();

        $quotation->refresh();
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('Discount too high', $quotation->approval_rejection_reason);
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $owner->id)->where('data->title', __('Quotation rejected'))->exists());
    }

    public function test_quotations_customer_facing_accept_and_reject_still_work_unaffected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), $this->quotationPayload($client));
        $quotation = Quotation::latest('id')->first();
        $this->assertSame('issued', $quotation->status);

        $this->actingAs($owner)->post(route('app.quotations.accept', $quotation))->assertSessionDoesntHaveErrors();
        $this->assertSame('accepted', $quotation->fresh()->status);

        $quotation2 = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'Q-2', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        $this->actingAs($owner)->post(route('app.quotations.reject', $quotation2))->assertSessionDoesntHaveErrors();
        $this->assertSame('rejected', $quotation2->fresh()->status);
    }
}

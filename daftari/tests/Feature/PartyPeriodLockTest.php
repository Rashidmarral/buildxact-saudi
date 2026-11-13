<?php

namespace Tests\Feature;

use App\Exceptions\PeriodLockedException;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PartyPeriodClosure;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "app have ability to close monthly record of supplier or client...
 * on quartely" (task #158) — a per-party mirror of the company-wide
 * accounting_lock_date: Client::lock_date/Supplier::lock_date,
 * enforced by LedgerPostingService::guardAgainstPartyLock() on every
 * post()/reverse(), independent of the company-wide lock.
 */
class PartyPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Locked Parties Co.', 'slug' => 'lp-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_an_invoice_dated_inside_a_locked_client_period_cannot_be_posted(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading', 'lock_date' => '2026-06-30']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-06-15', 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $this->expectException(PeriodLockedException::class);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice);
    }

    public function test_an_invoice_dated_after_the_client_lock_posts_normally(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading', 'lock_date' => '2026-06-30']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-07-01', 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $this->assertNotNull($entry);
    }

    public function test_locking_one_client_never_blocks_another_clients_invoice(): void
    {
        $company = $this->makeCompany();
        $lockedClient = Client::create(['company_id' => $company->id, 'name' => 'Locked Client', 'lock_date' => '2026-06-30']);
        $openClient = Client::create(['company_id' => $company->id, 'name' => 'Open Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $openClient->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-06-15', 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $this->assertNotNull($entry);
    }

    public function test_a_bill_dated_inside_a_locked_supplier_period_cannot_be_posted(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'lock_date' => '2026-06-30']);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'bill_date' => '2026-06-15', 'status' => 'posted', 'currency' => $company->currency,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);

        $this->expectException(PeriodLockedException::class);
        app(LedgerPostingService::class)->postBillPosted($bill);
    }

    public function test_the_company_wide_lock_still_applies_independently_of_any_party_lock(): void
    {
        $company = $this->makeCompany();
        $company->update(['accounting_lock_date' => '2026-05-31']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-05-15', 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $this->expectException(PeriodLockedException::class);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice);
    }

    public function test_owner_can_close_and_reopen_a_clients_period_from_the_account_statement_page(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading']);

        $this->actingAs($owner)->post(route('app.clients.close-period', $client), [
            'lock_date' => '2026-06-30',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-06-30', $client->fresh()->lock_date->toDateString());
        $this->assertNotNull(AuditLog::where('action', 'client.lock_date_update')->first());
        $closure = PartyPeriodClosure::where('company_id', $company->id)->where('party_type', 'client')->where('party_id', $client->id)->first();
        $this->assertSame('close', $closure->action);
        $this->assertSame($owner->id, $closure->performed_by);

        $this->actingAs($owner)->post(route('app.clients.reopen-period', $client), [
            'lock_date' => '',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($owner)->post(route('app.clients.reopen-period', $client), [
            'lock_date' => '', 'reason' => 'Correcting a late credit note',
        ])->assertSessionDoesntHaveErrors();

        $this->assertNull($client->fresh()->lock_date);
        $reopenClosure = PartyPeriodClosure::where('company_id', $company->id)->where('action', 'reopen')->first();
        $this->assertSame('Correcting a late credit note', $reopenClosure->reason);
    }

    public function test_closing_a_clients_period_cannot_move_the_lock_date_backward(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading', 'lock_date' => '2026-06-30']);

        $this->actingAs($owner)->post(route('app.clients.close-period', $client), [
            'lock_date' => '2026-05-31',
        ])->assertSessionHasErrors('lock_date');

        $this->assertSame('2026-06-30', $client->fresh()->lock_date->toDateString());
    }

    public function test_owner_can_close_and_reopen_a_suppliers_period(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        $this->actingAs($owner)->post(route('app.suppliers.close-period', $supplier), [
            'lock_date' => '2026-06-30',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-06-30', $supplier->fresh()->lock_date->toDateString());

        $this->actingAs($owner)->post(route('app.suppliers.reopen-period', $supplier), [
            'lock_date' => '', 'reason' => 'Reopening to fix a bill',
        ])->assertSessionDoesntHaveErrors();

        $this->assertNull($supplier->fresh()->lock_date);
    }

    public function test_the_account_statement_page_shows_the_close_period_card_for_the_selected_party(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Rashid Trading', 'lock_date' => '2026-06-30']);

        $page = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $page->assertOk()->assertSee('Close period')->assertSee('Locked through 2026-06-30');
    }
}

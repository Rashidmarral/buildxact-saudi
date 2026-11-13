<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\FxRevaluation;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: period-end revaluation of open foreign-currency AR/AP
 * balances — the unrealized counterpart to the realized FX gain/loss that
 * already posts when a foreign-currency invoice/bill is paid at a
 * different rate than it was issued at (see ForeignExchangeGainLossTest).
 * Each run always measures against a document's own booked exchange_rate,
 * and posting a new run reverses the previous one first so only the
 * latest unrealized adjustment ever stands.
 */
class FxRevaluationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'FX Reval Co.', 'slug' => 'fx-reval-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_create_form_lists_open_foreign_currency_invoices(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-USD-1',
            'issue_date' => now()->subDays(5), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.fx-revaluations.create'));

        $response->assertOk();
        $response->assertSee('INV-USD-1');
        $response->assertSee('USD');
    }

    public function test_a_company_with_only_base_currency_documents_has_nothing_to_revalue(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'SAR Client']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-SAR-1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'exchange_rate' => 1,
            'subtotal' => 500, 'vat_total' => 0, 'total' => 500, 'amount_paid' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.fx-revaluations.create'));

        $response->assertOk();
        $response->assertSee(__('Nothing to revalue — every open invoice and bill is in :currency.', ['currency' => $company->currency]));
    }

    public function test_a_stronger_current_rate_posts_an_unrealized_gain_and_moves_ar(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-USD-1',
            'issue_date' => now()->subDays(5), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 0,
        ]);

        $response = $this->actingAs($owner)->post(route('app.fx-revaluations.store'), [
            'as_of_date' => now()->toDateString(),
            'rates' => ['USD' => 3.80],
        ]);

        $response->assertRedirect();
        $revaluation = FxRevaluation::first();
        $this->assertNotNull($revaluation);
        $this->assertNotNull($revaluation->journal_entry_id);
        $this->assertSame(50.0, (float) $revaluation->lines->first()->unrealized_gain_loss);

        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();
        $fxGains = Account::where('company_id', $company->id)->where('code', '9100')->first();
        $entry = $revaluation->journalEntry;

        $this->assertSame(50.0, (float) $entry->lines->firstWhere('account_id', $ar->id)->debit);
        $this->assertSame(50.0, (float) $entry->lines->firstWhere('account_id', $fxGains->id)->credit);
    }

    public function test_a_stronger_current_rate_posts_an_unrealized_loss_on_a_payable(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'USD Supplier']);

        Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-USD-1',
            'status' => 'posted', 'bill_date' => now()->subDays(5)->toDateString(), 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 0,
        ]);

        $response = $this->actingAs($owner)->post(route('app.fx-revaluations.store'), [
            'as_of_date' => now()->toDateString(),
            'rates' => ['USD' => 3.80],
        ]);

        $response->assertRedirect();
        $revaluation = FxRevaluation::first();

        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $fxLosses = Account::where('company_id', $company->id)->where('code', '9200')->first();
        $entry = $revaluation->journalEntry;

        // The payable is now worth more in SAR — a credit (liability
        // increase) against AP, and the mirror-image loss on the company.
        $this->assertSame(50.0, (float) $entry->lines->firstWhere('account_id', $ap->id)->credit);
        $this->assertSame(50.0, (float) $entry->lines->firstWhere('account_id', $fxLosses->id)->debit);
    }

    public function test_posting_a_new_revaluation_reverses_the_previous_one(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-USD-1',
            'issue_date' => now()->subDays(5), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($owner)->post(route('app.fx-revaluations.store'), [
            'as_of_date' => now()->toDateString(),
            'rates' => ['USD' => 3.80],
        ]);
        $first = FxRevaluation::first();

        $this->actingAs($owner)->post(route('app.fx-revaluations.store'), [
            'as_of_date' => now()->toDateString(),
            'rates' => ['USD' => 3.70],
        ]);

        $first->refresh();
        $this->assertNotNull($first->reversed_at);

        $reversal = JournalEntry::where('company_id', $company->id)
            ->where('source_type', 'fx_revaluation_reversal')
            ->where('source_id', $first->id)
            ->first();
        $this->assertNotNull($reversal);

        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();
        // The reversal undoes the first run's +50 gain; the second run
        // (3.70, weaker than the invoice's own booked 3.75) posts a fresh
        // -50 loss measured from the invoice's original rate, not the
        // reversed one — so AR nets to a 50 credit, not 100.
        $this->assertSame(50.0, (float) $reversal->lines->firstWhere('account_id', $ar->id)->credit);

        $second = FxRevaluation::where('id', '!=', $first->id)->first();
        $fxLosses = Account::where('company_id', $company->id)->where('code', '9200')->first();
        $this->assertSame(50.0, (float) $second->journalEntry->lines->firstWhere('account_id', $ar->id)->credit);
        $this->assertSame(50.0, (float) $second->journalEntry->lines->firstWhere('account_id', $fxLosses->id)->debit);
    }

    public function test_a_fully_paid_invoice_is_excluded(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-USD-PAID',
            'issue_date' => now()->subDays(5), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'amount_paid' => 1000,
        ]);

        $response = $this->actingAs($owner)->get(route('app.fx-revaluations.create'));

        $response->assertOk();
        $response->assertDontSee('INV-USD-PAID');
    }
}

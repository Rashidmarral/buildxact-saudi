<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: "Manual journal entries" — the ledger only ever got
 * entries auto-posted from a document (invoice/bill/payment/expense);
 * there was no way to record an adjustment, correction, or opening
 * balance with no source document of its own. Adds a "New manual entry"
 * flow (ManualJournalEntryController + LedgerPostingService::postManual())
 * that goes through the same balance and period-lock validation as every
 * other posting, plus a "Reverse" action for undoing one via an offsetting
 * entry (reusing the existing generic reverse()).
 */
class ManualJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Manual JE Co.', 'slug' => 'manual-je-'.uniqid(),
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_new_manual_entry_form_renders(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.journals.manual.create'));

        $response->assertOk();
        $response->assertSee('New manual journal entry');
    }

    public function test_a_balanced_manual_entry_posts_successfully(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Owner capital injection',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $response->assertRedirect();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Owner capital injection', $entry->description);
        $this->assertSame($entry->id, $entry->source_id);
        $this->assertSame(5000.0, $entry->totalDebit());
        $this->assertSame(5000.0, $entry->totalCredit());
    }

    public function test_an_unbalanced_manual_entry_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 4000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
    }

    public function test_a_manual_entry_dated_inside_a_locked_period_is_rejected(): void
    {
        $company = $this->makeCompany(['accounting_lock_date' => now()->subMonth()->toDateString()]);
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->subMonths(2)->toDateString(),
            'description' => 'Locked period attempt',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('entry_date');
        $this->assertSame(0, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->count());
    }

    public function test_a_manual_entry_can_be_reversed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'To be reversed',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 250, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 250],
            ],
        ]);

        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->first();

        $response = $this->actingAs($owner)->post(route('app.journals.reverse', $entry));
        $response->assertRedirect();

        $reversal = JournalEntry::where('company_id', $company->id)->where('source_type', 'manual_reversal')->first();
        $this->assertNotNull($reversal);
        $this->assertSame(250.0, (float) $reversal->lines->firstWhere('account_id', $cash->id)->credit);
        $this->assertSame(250.0, (float) $reversal->lines->firstWhere('account_id', $equity->id)->debit);

        // Reversing again is a no-op — the button disappears once reversed,
        // but the route itself must not double-post either.
        $second = $this->actingAs($owner)->post(route('app.journals.reverse', $entry));
        $second->assertSessionHasErrors('reverse');
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual_reversal')->count());
    }

    public function test_a_document_generated_entry_cannot_be_reversed_from_this_action(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $entry = JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-00099', 'entry_date' => now(),
            'source_type' => 'invoice', 'source_id' => 1, 'description' => 'Invoice posting',
        ]);

        $response = $this->actingAs($owner)->post(route('app.journals.reverse', $entry));

        $response->assertForbidden();
    }
}

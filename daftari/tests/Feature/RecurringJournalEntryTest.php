<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature request: "Recurring/scheduled journal entries" — for things like
 * monthly depreciation or accruals that repeat on a schedule, mirroring
 * how Recurring Invoices already work. journals:generate-recurring posts
 * a real JournalEntry (via LedgerPostingService::postManual(), so it gets
 * the same balance/period-lock validation as a hand-entered manual entry)
 * for every active recurrence whose next_run_date has arrived, then
 * advances it.
 */
class RecurringJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Recurring JE Co.', 'slug' => 'recurring-je-'.uniqid(),
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_new_and_edit_recurring_entry_forms_render(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $rent = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();

        $createResponse = $this->actingAs($owner)->get(route('app.recurring-journal-entries.create'));
        $createResponse->assertOk();

        $recurring = RecurringJournalEntry::create([
            'company_id' => $company->id, 'title' => 'Existing recurrence', 'frequency' => 'monthly',
            'start_date' => now()->toDateString(), 'next_run_date' => now()->toDateString(), 'status' => 'active',
        ]);
        $recurring->lines()->createMany([
            ['account_id' => $rent->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 500],
        ]);

        $editResponse = $this->actingAs($owner)->get(route('app.recurring-journal-entries.edit', $recurring));
        $editResponse->assertOk();
        $editResponse->assertSee('Existing recurrence');

        $indexResponse = $this->actingAs($owner)->get(route('app.recurring-journal-entries.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Existing recurrence');
    }

    public function test_creating_a_recurring_journal_entry_via_the_form(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $rent = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();

        $response = $this->actingAs($owner)->post(route('app.recurring-journal-entries.store'), [
            'title' => 'Monthly rent accrual',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $rent->id, 'debit' => 3000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 3000],
            ],
        ]);

        $response->assertRedirect(route('app.recurring-journal-entries.index'));
        $recurring = RecurringJournalEntry::where('company_id', $company->id)->first();
        $this->assertNotNull($recurring);
        $this->assertSame('Monthly rent accrual', $recurring->title);
        $this->assertCount(2, $recurring->lines);
    }

    public function test_an_unbalanced_recurring_entry_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $rent = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();

        $response = $this->actingAs($owner)->post(route('app.recurring-journal-entries.store'), [
            'title' => 'Unbalanced',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $rent->id, 'debit' => 3000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 2000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, RecurringJournalEntry::where('company_id', $company->id)->count());
    }

    public function test_the_generate_recurring_command_posts_a_due_entry_and_advances_the_schedule(): void
    {
        $company = $this->makeCompany();
        $rent = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();

        $recurring = RecurringJournalEntry::create([
            'company_id' => $company->id, 'title' => 'Monthly rent accrual', 'frequency' => 'monthly',
            'start_date' => now()->subDay()->toDateString(), 'next_run_date' => now()->subDay()->toDateString(),
            'status' => 'active',
        ]);
        $recurring->lines()->createMany([
            ['account_id' => $rent->id, 'debit' => 3000, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 3000],
        ]);

        Artisan::call('journals:generate-recurring');

        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'manual')->first();
        $this->assertNotNull($entry);
        $this->assertSame(3000.0, $entry->totalDebit());

        $recurring->refresh();
        $this->assertSame(1, $recurring->generated_count);
        $this->assertTrue($recurring->next_run_date->gt(now()));
    }

    public function test_a_recurrence_past_its_end_date_is_marked_completed_after_generating(): void
    {
        $company = $this->makeCompany();
        $rent = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();

        $recurring = RecurringJournalEntry::create([
            'company_id' => $company->id, 'title' => 'Final posting', 'frequency' => 'monthly',
            'start_date' => now()->subDay()->toDateString(), 'next_run_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(), 'status' => 'active',
        ]);
        $recurring->lines()->createMany([
            ['account_id' => $rent->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
        ]);

        $recurring->generateEntry();

        $recurring->refresh();
        $this->assertSame('completed', $recurring->status);
    }

    public function test_pausing_and_resuming_a_recurring_journal_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $recurring = RecurringJournalEntry::create([
            'company_id' => $company->id, 'title' => 'Pausable', 'frequency' => 'monthly',
            'start_date' => now()->toDateString(), 'next_run_date' => now()->toDateString(), 'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('app.recurring-journal-entries.pause', $recurring));
        $this->assertSame('paused', $recurring->fresh()->status);

        $this->actingAs($owner)->post(route('app.recurring-journal-entries.resume', $recurring));
        $this->assertSame('active', $recurring->fresh()->status);
    }
}

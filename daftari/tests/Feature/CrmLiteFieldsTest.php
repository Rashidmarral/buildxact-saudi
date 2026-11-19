<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-29: Client and Supplier had no CRM trail at all — no
 * lead source, no next-follow-up reminder, no record of the last time
 * anyone actually reached out. Both models gain lead_source,
 * next_follow_up_date, and last_contacted_at; the latter is set via a
 * one-click "Log contact" action rather than being directly editable.
 */
class CrmLiteFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'CRM Co.', 'slug' => 'crm-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_saving_a_client_persists_lead_source_and_next_follow_up_date(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.clients.store'), [
            'client_code' => 'CL-'.uniqid(),
            'type' => 'company',
            'name' => 'Prospect LLC',
            'lead_source' => 'Referral',
            'next_follow_up_date' => now()->addDays(5)->toDateString(),
        ]);

        $client = Client::where('name', 'Prospect LLC')->first();
        $response->assertRedirect(route('app.clients.index'));
        $this->assertNotNull($client);
        $this->assertSame('Referral', $client->lead_source);
        $this->assertSame(now()->addDays(5)->toDateString(), $client->next_follow_up_date->toDateString());
    }

    public function test_logging_contact_on_a_client_timestamps_last_contacted_at(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Prospect LLC']);
        $this->assertNull($client->last_contacted_at);

        $response = $this->actingAs($owner)->post(route('app.clients.log-contact', $client));

        $response->assertRedirect();
        $this->assertNotNull($client->fresh()->last_contacted_at);
    }

    public function test_logging_contact_on_a_supplier_timestamps_last_contacted_at(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Vendor LLC']);
        $this->assertNull($supplier->last_contacted_at);

        $response = $this->actingAs($owner)->post(route('app.suppliers.log-contact', $supplier));

        $response->assertRedirect();
        $this->assertNotNull($supplier->fresh()->last_contacted_at);
    }

    public function test_the_client_index_flags_an_overdue_follow_up(): void
    {
        $owner = $this->makeOwner();
        Client::create(['company_id' => $owner->company_id, 'name' => 'Overdue Prospect', 'next_follow_up_date' => now()->subDays(2)->toDateString()]);

        $response = $this->actingAs($owner)->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertSee('text-red-600', false);
    }
}

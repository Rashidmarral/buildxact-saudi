<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Competitive gap: Client already carried lead_source/next_follow_up_date/
 * last_contacted_at ("CRM-lite" fields, finding LOW-29), but nothing let a
 * prospect be tracked through a pipeline before becoming a paying client —
 * every rival accounting SaaS surveyed bundles at least this much. Adds a
 * pipeline_stage on the same Client record (no separate lead entity or
 * conversion step) and a board that only ever shows clients someone has
 * explicitly opted into it.
 */
class ClientPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Pipeline Co.', 'slug' => 'pipeline-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_client_can_be_created_with_a_pipeline_stage(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.clients.store'), [
            'client_code' => 'CLI-000001', 'type' => 'company', 'name' => 'Al Rashid Trading',
            'pipeline_stage' => 'prospect', 'lead_source' => 'Referral',
        ])->assertRedirect();

        $client = Client::first();
        $this->assertSame('prospect', $client->pipeline_stage);
        $this->assertTrue($client->isInOpenPipeline());
    }

    public function test_a_client_with_no_stage_chosen_is_not_tracked_in_the_pipeline(): void
    {
        $owner = $this->makeOwner();
        Client::create(['company_id' => $owner->company_id, 'name' => 'Plain Client']);

        $this->actingAs($owner)->get(route('app.clients.pipeline'))
            ->assertOk()->assertDontSee('Plain Client');
    }

    public function test_the_pipeline_board_groups_clients_by_stage(): void
    {
        $owner = $this->makeOwner();
        Client::create(['company_id' => $owner->company_id, 'name' => 'New Lead', 'pipeline_stage' => 'prospect']);
        Client::create(['company_id' => $owner->company_id, 'name' => 'Deep In Talks', 'pipeline_stage' => 'negotiating']);

        $response = $this->actingAs($owner)->get(route('app.clients.pipeline'));

        $response->assertOk()->assertSeeInOrder(['New Lead', 'Deep In Talks']);
    }

    public function test_advancing_a_stage_updates_the_client(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Rising Prospect', 'pipeline_stage' => 'prospect']);

        $this->actingAs($owner)->post(route('app.clients.advance-stage', $client), [
            'pipeline_stage' => 'qualified',
        ])->assertRedirect();

        $this->assertSame('qualified', $client->fresh()->pipeline_stage);
    }

    public function test_moving_to_lost_can_record_a_reason(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Cold Prospect', 'pipeline_stage' => 'contacted']);

        $this->actingAs($owner)->post(route('app.clients.advance-stage', $client), [
            'pipeline_stage' => 'lost', 'lost_reason' => 'Chose a competitor',
        ])->assertRedirect();

        $fresh = $client->fresh();
        $this->assertSame('lost', $fresh->pipeline_stage);
        $this->assertSame('Chose a competitor', $fresh->lost_reason);
        $this->assertFalse($fresh->isInOpenPipeline());
    }

    public function test_moving_away_from_lost_clears_the_stale_lost_reason(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create([
            'company_id' => $owner->company_id, 'name' => 'Reconsidered Prospect',
            'pipeline_stage' => 'lost', 'lost_reason' => 'Budget',
        ]);

        $this->actingAs($owner)->post(route('app.clients.advance-stage', $client), [
            'pipeline_stage' => 'negotiating',
        ])->assertRedirect();

        $fresh = $client->fresh();
        $this->assertSame('negotiating', $fresh->pipeline_stage);
        $this->assertNull($fresh->lost_reason);
    }

    public function test_a_company_cannot_advance_another_companys_client(): void
    {
        $ownerA = $this->makeOwner();
        $ownerB = $this->makeOwner();
        $clientB = Client::create(['company_id' => $ownerB->company_id, 'name' => 'Other Co Client', 'pipeline_stage' => 'prospect']);

        $this->actingAs($ownerA)->post(route('app.clients.advance-stage', $clientB), [
            'pipeline_stage' => 'won',
        ])->assertNotFound();

        $this->assertSame('prospect', $clientB->fresh()->pipeline_stage);
    }

    public function test_an_invalid_stage_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Odd Client', 'pipeline_stage' => 'prospect']);

        $this->actingAs($owner)->post(route('app.clients.advance-stage', $client), [
            'pipeline_stage' => 'not-a-real-stage',
        ])->assertSessionHasErrors('pipeline_stage');

        $this->assertSame('prospect', $client->fresh()->pipeline_stage);
    }
}

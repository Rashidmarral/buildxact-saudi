<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-22: Quotation::isExpired() and the quotations index
 * "Expired" filter tab already existed, but nothing ever set a
 * quotation's status column to 'expired' — the tab's count query filters
 * on status='expired' directly, so it always showed 0. The new
 * quotations:expire command (scheduled daily) now flips issued quotations
 * past their expiry_date and notifies the creator in-app.
 */
class QuotationExpiryAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerAndClient(): array
    {
        $company = Company::create(['name' => 'Quote Co.', 'slug' => 'quote-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'C-1', 'type' => 'company', 'name' => 'A Client']);

        return [$owner, $client];
    }

    public function test_an_issued_quotation_past_its_expiry_date_is_marked_expired(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();

        $quotation = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QT-1', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->subDays(40), 'expiry_date' => now()->subDays(10),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        Artisan::call('quotations:expire');

        $this->assertEquals('expired', $quotation->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_the_expired_tab_count_reflects_the_automated_transition(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();

        Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QT-1', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->subDays(40), 'expiry_date' => now()->subDays(10),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        Artisan::call('quotations:expire');

        $response = $this->actingAs($owner)->get(route('app.quotations.index'));
        $response->assertOk();
        $response->assertSee(__('Expired') . ' (1)');
    }

    public function test_a_quotation_not_yet_past_expiry_is_left_alone(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();

        $quotation = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QT-1', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        Artisan::call('quotations:expire');

        $this->assertEquals('issued', $quotation->fresh()->status);
    }

    public function test_a_draft_or_already_accepted_quotation_is_never_auto_expired(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();

        $draft = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QT-1', 'type' => 'quotation', 'status' => 'draft',
            'issue_date' => now()->subDays(40), 'expiry_date' => now()->subDays(10),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
        $accepted = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QT-2', 'type' => 'quotation', 'status' => 'accepted',
            'issue_date' => now()->subDays(40), 'expiry_date' => now()->subDays(10),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        Artisan::call('quotations:expire');

        $this->assertEquals('draft', $draft->fresh()->status);
        $this->assertEquals('accepted', $accepted->fresh()->status);
    }
}

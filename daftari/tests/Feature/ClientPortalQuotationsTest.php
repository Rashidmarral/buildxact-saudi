<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-14: the client portal only ever covered invoices —
 * a quotation reached a client purely as an emailed PDF, with no online
 * page to view it or accept/reject it. This adds a token-authenticated
 * public quotation page (mirroring the existing public invoice pattern)
 * plus a "Quotes" tab on the logged-in client portal.
 */
class ClientPortalQuotationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(string $status = 'issued', array $overrides = []): array
    {
        $company = Company::create(['name' => 'Quote Co.', 'slug' => 'quote-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Prospect LLC', 'email' => 'prospect@example.com']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $quotation = Quotation::create(array_merge([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QTN-'.uniqid(), 'type' => 'quotation', 'status' => $status,
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(14)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ], $overrides));

        return [$company, $client, $owner, $quotation];
    }

    public function test_a_public_quotation_page_generates_a_token_automatically(): void
    {
        [, , , $quotation] = $this->makeQuotation();

        $this->assertNotEmpty($quotation->public_token);
        $this->assertSame(40, strlen($quotation->public_token));
    }

    public function test_the_public_page_shows_an_issued_quotation_with_a_valid_token(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued');

        $response = $this->get(route('public.quotations.show', [$quotation->id, $quotation->public_token]));

        $response->assertOk();
        $response->assertSee($quotation->quotation_number);
    }

    public function test_the_public_page_404s_on_a_wrong_token(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued');

        $response = $this->get(route('public.quotations.show', [$quotation->id, 'not-the-real-token']));

        $response->assertNotFound();
    }

    public function test_a_draft_quotation_is_not_publicly_viewable(): void
    {
        [, , , $quotation] = $this->makeQuotation('draft');

        $response = $this->get(route('public.quotations.show', [$quotation->id, $quotation->public_token]));

        $response->assertNotFound();
    }

    public function test_accepting_an_issued_quotation_marks_it_accepted(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued');

        $response = $this->post(route('public.quotations.accept', [$quotation->id, $quotation->public_token]), [
            'accepted_by_name' => 'Jane Prospect',
            'accepted_signature' => 'data:image/png;base64,'.base64_encode('fake-png-bytes'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $fresh = $quotation->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertSame('Jane Prospect', $fresh->accepted_by_name);
        $this->assertNotNull($fresh->accepted_at);
        $this->assertNotNull($fresh->accepted_signature);
        $this->assertSame('127.0.0.1', $fresh->accepted_ip);
    }

    public function test_accepting_without_a_signature_fails_validation_and_does_not_change_status(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued');

        $response = $this->post(route('public.quotations.accept', [$quotation->id, $quotation->public_token]), [
            'accepted_by_name' => 'Jane Prospect',
        ]);

        $response->assertSessionHasErrors('accepted_signature');
        $this->assertSame('issued', $quotation->fresh()->status);
    }

    public function test_rejecting_an_issued_quotation_marks_it_rejected(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued');

        $this->post(route('public.quotations.reject', [$quotation->id, $quotation->public_token]));

        $this->assertSame('rejected', $quotation->fresh()->status);
    }

    public function test_a_converted_quotation_cannot_be_accepted_again(): void
    {
        [, , , $quotation] = $this->makeQuotation('converted');

        $this->post(route('public.quotations.accept', [$quotation->id, $quotation->public_token]));

        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function test_an_expired_quotation_cannot_be_accepted(): void
    {
        [, , , $quotation] = $this->makeQuotation('issued', ['expiry_date' => now()->subDay()->toDateString()]);

        $this->post(route('public.quotations.accept', [$quotation->id, $quotation->public_token]));

        $this->assertSame('issued', $quotation->fresh()->status);
    }

    public function test_the_portal_quotes_tab_lists_issued_quotations_but_not_drafts(): void
    {
        [, $client, , $quotation] = $this->makeQuotation('issued');
        Quotation::create([
            'company_id' => $client->company_id, 'client_id' => $client->id,
            'quotation_number' => 'QTN-draft-'.uniqid(), 'type' => 'quotation', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR', 'subtotal' => 500, 'vat_total' => 75, 'total' => 575,
        ]);

        $response = $this->withSession(['portal_client_id' => $client->id])->get(route('portal.quotations'));

        $response->assertOk();
        $response->assertSee($quotation->quotation_number);
        $response->assertViewHas('quotations', fn ($quotations) => $quotations->total() === 1);
    }

    public function test_the_portal_quotes_tab_requires_a_client_session(): void
    {
        $response = $this->get(route('portal.quotations'));

        $response->assertRedirect(route('portal.login'));
    }
}

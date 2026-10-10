<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-28: a quotation's status flipped straight from
 * "issued" to "accepted" with nothing behind it — no record of who
 * actually said yes, when, from where, or any signature. The public
 * accept form now requires a typed name and a hand-drawn signature
 * (captured as a PNG data URL), and PublicQuotationController::accept()
 * stores them alongside the accepting IP and timestamp; staff marking a
 * quotation accepted internally records their own name instead. The
 * acceptance record then shows on the quotation's staff-facing page.
 */
class QuotationAcceptanceRecordTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(): array
    {
        $company = Company::create(['name' => 'Record Co.', 'slug' => 'record-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Prospect LLC']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QTN-'.uniqid(), 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(14)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        return [$owner, $quotation];
    }

    public function test_a_client_accepting_online_leaves_a_full_acceptance_record_visible_to_staff(): void
    {
        [$owner, $quotation] = $this->makeQuotation();

        $this->post(route('public.quotations.accept', [$quotation->id, $quotation->public_token]), [
            'accepted_by_name' => 'Jane Prospect',
            'accepted_signature' => 'data:image/png;base64,'.base64_encode('fake-png-bytes'),
        ]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));
        $response->assertOk();
        $response->assertSee('Jane Prospect');
        $response->assertSee(__('Acceptance record'));
    }

    public function test_staff_marking_a_quotation_accepted_internally_records_their_own_name(): void
    {
        [$owner, $quotation] = $this->makeQuotation();

        $this->actingAs($owner)->post(route('app.quotations.accept', $quotation));

        $fresh = $quotation->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertSame($owner->name, $fresh->accepted_by_name);
        $this->assertNotNull($fresh->accepted_at);
        $this->assertNull($fresh->accepted_signature);
    }
}

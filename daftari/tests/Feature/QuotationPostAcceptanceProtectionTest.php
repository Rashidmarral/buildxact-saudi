<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding D-2: unlike every other document type
 * (Invoice/Bill/CreditNote/DebitNote/PurchaseOrder all block edits and
 * deletes once sent/posted), QuotationController::update()/destroy() had
 * no status check at all — a quotation could be silently edited or
 * deleted even after a client had digitally accepted it (Quotation
 * carries accepted_at/accepted_by_name/accepted_signature/accepted_ip)
 * or after it had already been converted to a real invoice.
 */
class QuotationPostAcceptanceProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(array $overrides = []): array
    {
        $company = Company::create(['name' => 'Record Co.', 'slug' => 'record-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Prospect LLC']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $quotation = Quotation::create(array_merge([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QTN-'.uniqid(), 'type' => 'quotation', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ], $overrides));

        return [$owner, $quotation];
    }

    public function test_a_draft_quotation_can_still_be_edited(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['status' => 'draft']);

        $response = $this->actingAs($owner)->get(route('app.quotations.edit', $quotation));

        $response->assertOk();
    }

    public function test_an_accepted_quotation_cannot_be_opened_for_editing(): void
    {
        [$owner, $quotation] = $this->makeQuotation([
            'status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => 'Jane Prospect',
        ]);

        $response = $this->actingAs($owner)->get(route('app.quotations.edit', $quotation));

        $response->assertRedirect(route('app.quotations.show', $quotation));
        $response->assertSessionHasErrors('quotation');
    }

    public function test_an_accepted_quotation_cannot_be_updated_even_by_posting_directly_to_the_update_route(): void
    {
        [$owner, $quotation] = $this->makeQuotation([
            'status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => 'Jane Prospect',
        ]);
        $originalTotal = $quotation->total;

        $response = $this->actingAs($owner)->put(route('app.quotations.update', $quotation), [
            'client_id' => $quotation->client_id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Tampered line', 'quantity' => 1, 'unit_price' => 99999, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect(route('app.quotations.show', $quotation));
        $response->assertSessionHasErrors('quotation');
        $this->assertSame((float) $originalTotal, (float) $quotation->fresh()->total, 'The accepted quotation must not be silently rewritten.');
        $this->assertSame(0, $quotation->fresh()->items()->count());
    }

    public function test_a_converted_quotation_cannot_be_updated(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['status' => 'converted']);

        $response = $this->actingAs($owner)->put(route('app.quotations.update', $quotation), [
            'client_id' => $quotation->client_id,
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'vat_rate' => 15]],
        ]);

        $response->assertSessionHasErrors('quotation');
    }

    public function test_an_accepted_quotation_cannot_be_deleted(): void
    {
        [$owner, $quotation] = $this->makeQuotation([
            'status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => 'Jane Prospect',
        ]);

        $response = $this->actingAs($owner)->delete(route('app.quotations.destroy', $quotation));

        $response->assertSessionHasErrors('quotation');
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_a_converted_quotation_cannot_be_deleted(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['status' => 'converted']);

        $response = $this->actingAs($owner)->delete(route('app.quotations.destroy', $quotation));

        $response->assertSessionHasErrors('quotation');
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_an_issued_quotation_cannot_be_deleted(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['status' => 'issued']);

        $response = $this->actingAs($owner)->delete(route('app.quotations.destroy', $quotation));

        $response->assertSessionHasErrors('quotation');
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_a_draft_quotation_can_still_be_deleted(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['status' => 'draft']);

        $response = $this->actingAs($owner)->delete(route('app.quotations.destroy', $quotation));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
    }
}

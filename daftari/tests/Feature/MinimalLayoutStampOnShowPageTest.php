<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client request forwarded by the operator: "please sign and stamp the
 * attached PO and share the proforma invoice along with your bank
 * details". The downloaded PDF (documents/print/pdf.blade.php) already
 * printed the company's uploaded stamp on the default "minimal" layout
 * (via the shared pdf-notes-stamp partial) — but the in-browser "show"
 * page (documents/print/body.blade.php) never did for that same layout;
 * every other layout showed it, minimal/bordered/boxed just didn't. A
 * company with no InvoiceTemplate row at all (like a freshly seeded one)
 * falls back to exactly this layout, so its stamp was invisible on the
 * page the operator actually looks at before downloading.
 */
class MinimalLayoutStampOnShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_company_stamp_renders_on_the_show_page_under_the_default_layout(): void
    {
        Storage::fake('public');

        $company = Company::create(['name' => 'Stamp Co.', 'slug' => 'stamp-'.uniqid()]);
        $stamp = UploadedFile::fake()->image('stamp.png');
        $path = $stamp->store('stamps', 'public');
        $company->update(['stamp_path' => $path]);

        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'PRO-1',
            'type' => 'proforma', 'status' => 'draft', 'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $quotation->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        // No InvoiceTemplate seeded for this company at all — the exact
        // shape that falls back to $layout = 'minimal' in documents/
        // print/body.blade.php.
        $this->assertNull($company->defaultTemplateFor('proforma'));

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee(Storage::url($path), false);
    }
}

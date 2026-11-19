<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Account Statement's running "Balance" column is written as a formula
 * chain (each line references the balance just above it), same
 * convention as General Ledger — this confirms the chain computes to
 * the correct closing balance after an invoice and a partial payment.
 */
class AccountStatementExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_running_balance_chain_computes_the_correct_closing_balance(): void
    {
        $company = Company::create(['name' => 'Statement Co.', 'slug' => 'statement-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Acme Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 400, 'paid_at' => now(), 'reference' => 'BT-1']);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id, 'export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'as-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $closingRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Closing balance')) {
                $closingRow = $r;
            }
        }
        $this->assertNotNull($closingRow);

        $cell = $sheet->getCell('E'.$closingRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(750.0, (float) $cell->getCalculatedValue(), 0.01);
    }
}

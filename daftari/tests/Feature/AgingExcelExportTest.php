<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Each party's Total column in the aging summary export is a formula
 * (SUM of that row's 5 buckets), and the grand Total row sums every
 * party row — this confirms both actually compute correctly, and that
 * the details export's Total row does too.
 */
class AgingExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company, Client $client, float $total, ?string $dueDate): Invoice
    {
        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->subDays(100)->toDateString(),
            'due_date' => $dueDate, 'currency' => $company->currency, 'total' => $total,
        ]);
    }

    public function test_the_summary_exports_total_column_is_a_formula_summing_the_aging_buckets(): void
    {
        $company = Company::create(['name' => 'Aging Co.', 'slug' => 'aging-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Overdue Client']);

        $this->makeInvoice($company, $client, 1000, now()->subDays(100)->toDateString()); // far overdue
        $this->makeInvoice($company, $client, 500, now()->addDays(10)->toDateString()); // current

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['type' => 'receivables', 'export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'aging-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $partyRow = null;
        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $value = (string) $sheet->getCell('A'.$r)->getValue();
            if ($value === 'Overdue Client') {
                $partyRow = $r;
            }
            if ($value === __('Total')) {
                $totalRow = $r;
            }
        }

        $this->assertNotNull($partyRow);
        $this->assertNotNull($totalRow);

        $partyCell = $sheet->getCell('G'.$partyRow);
        $this->assertStringStartsWith('=SUM', (string) $partyCell->getValue());
        $this->assertEqualsWithDelta(1500.0, (float) $partyCell->getCalculatedValue(), 0.01);

        $totalCell = $sheet->getCell('G'.$totalRow);
        $this->assertStringStartsWith('=SUM', (string) $totalCell->getValue());
        $this->assertEqualsWithDelta(1500.0, (float) $totalCell->getCalculatedValue(), 0.01);
    }

    public function test_the_details_export_total_row_sums_every_due_amount(): void
    {
        $company = Company::create(['name' => 'Aging Co.', 'slug' => 'aging-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Detail Client']);

        $this->makeInvoice($company, $client, 750, now()->subDays(50)->toDateString());
        $this->makeInvoice($company, $client, 250, now()->subDays(5)->toDateString());

        $response = $this->actingAs($owner)->get(route('app.reports.aging-details', ['type' => 'receivables', 'export' => 'excel']));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'aging-details-xlsx-test-').'.xlsx';
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $totalRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $totalRow = $r;
            }
        }
        $this->assertNotNull($totalRow);

        $cell = $sheet->getCell('F'.$totalRow);
        $this->assertStringStartsWith('=SUM', (string) $cell->getValue());
        $this->assertEqualsWithDelta(1000.0, (float) $cell->getCalculatedValue(), 0.01);
    }
}

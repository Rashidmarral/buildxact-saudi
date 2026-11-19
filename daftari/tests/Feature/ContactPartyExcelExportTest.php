<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Excel export coverage for the contact/party-side reports: P&L by
 * Cost Centre/Location (Net = Income − Expenses, a per-row formula),
 * Receivables/Payables by Contact (Closing = Opening + Debit − Credit),
 * Employee Advances (Outstanding = Advanced − Settled − Spent), and
 * smoke checks for Customer/Supplier Report (plain SimpleTableExcelExporter
 * totals, already covered generically).
 */
class ContactPartyExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'cp-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_profit_loss_by_cost_centre_and_location_net_column_is_a_formula_that_computes_correctly(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $sales = Account::where('company_id', $company->id)->where('code', '4000')->first();
        $opex = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $svc = app(LedgerPostingService::class);
        $svc->post($company, 'manual', 1, 'Sale', now(), [
            ['account_id' => $bank->id, 'debit' => 9000],
            ['account_id' => $sales->id, 'credit' => 9000],
        ]);
        $svc->post($company, 'manual', 2, 'Opex', now(), [
            ['account_id' => $opex->id, 'debit' => 3000],
            ['account_id' => $bank->id, 'credit' => 3000],
        ]);

        foreach (['profit-loss-by-cost-centre', 'profit-loss-by-location'] as $route) {
            $response = $this->actingAs($owner)->get(route('app.reports.'.$route, ['export' => 'excel']));
            $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
            $unassignedRow = null;
            foreach (range(1, $sheet->getHighestRow()) as $r) {
                if ($sheet->getCell('A'.$r)->getValue() === __('Unassigned')) {
                    $unassignedRow = $r;
                }
            }
            $this->assertNotNull($unassignedRow, "Unassigned row not found for {$route}");

            $cell = $sheet->getCell('D'.$unassignedRow);
            $this->assertStringStartsWith('=', (string) $cell->getValue());
            $this->assertEqualsWithDelta(6000.0, (float) $cell->getCalculatedValue(), 0.01);
        }
    }

    public function test_receivables_by_contact_closing_balance_is_a_formula_that_computes_correctly(): void
    {
        $company = Company::create(['name' => 'Contact Balances Co.', 'slug' => 'contact-bal-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);

        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-OLD',
            'issue_date' => now()->startOfMonth()->subMonth()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 0, 'total' => 10000, 'amount_paid' => 0,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-NEW',
            'issue_date' => now()->startOfMonth()->toDateString(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000, 'amount_paid' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.receivables-payables-by-contact', [
            'period' => 'custom', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
            'export' => 'excel',
        ]));
        $response->assertOk();

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $partyRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === 'Al Jeul Construction') {
                $partyRow = $r;
            }
        }
        $this->assertNotNull($partyRow);

        $cell = $sheet->getCell('E'.$partyRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(14000.0, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_employee_advances_outstanding_column_is_a_formula_that_computes_correctly(): void
    {
        $company = Company::create(['name' => 'Advance Co.', 'slug' => 'advance-co-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
        $employee = Employee::create([
            'company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => 'Saeed Al-Qahtani',
            'hire_date' => now()->subYear()->toDateString(), 'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payee_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ]);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payer_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 1200, 'method' => 'cash',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.employee-advances', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $employeeRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === 'Saeed Al-Qahtani') {
                $employeeRow = $r;
            }
        }
        $this->assertNotNull($employeeRow);

        $cell = $sheet->getCell('F'.$employeeRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(1800.0, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_customer_and_supplier_report_excel_exports_download_correctly(): void
    {
        $company = Company::create(['name' => 'Party Report Co.', 'slug' => 'party-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Acme Trading', 'vat_number' => '300000000000003']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-A1',
            'issue_date' => now()->startOfMonth(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 500,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 500, 'paid_at' => now()->startOfMonth()->addDays(2)]);

        $customers = $this->actingAs($owner)->get(route('app.reports.customers', ['export' => 'excel']));
        $customers->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($customers->getContent())->getActiveSheet();
        $row = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === 'Acme Trading') {
                $row = $r;
            }
        }
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(650.0, (float) $sheet->getCell('G'.$row)->getCalculatedValue(), 0.01);

        $suppliers = $this->actingAs($owner)->get(route('app.reports.suppliers', ['export' => 'excel']));
        $suppliers->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

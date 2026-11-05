<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatReturnPeriodPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recorded_period_can_be_downloaded_as_a_pdf(): void
    {
        $company = Company::create(['name' => 'Test Co', 'name_ar' => 'شركة تجريبية', 'slug' => 'test-'.uniqid(), 'vat_number' => '300012345600003']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $period = $company->vatReturnPeriods()->create([
            'created_by' => $owner->id, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'output_tax' => 1000, 'input_tax_purchases' => 400, 'expense_tax' => 100, 'net_recoverable_input_tax' => 500,
            'credit_brought_forward' => 0, 'amount_payable' => 500, 'credit_carried_forward' => 0, 'notes' => 'Filed on ZATCA portal 2026-04-10.',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_credit_position_period_still_downloads_cleanly(): void
    {
        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $period = $company->vatReturnPeriods()->create([
            'created_by' => $owner->id, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'output_tax' => 100, 'input_tax_purchases' => 400, 'expense_tax' => 100, 'net_recoverable_input_tax' => 500,
            'credit_brought_forward' => 0, 'amount_payable' => 0, 'credit_carried_forward' => 400,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.pdf', $period));

        $response->assertOk();
    }
}

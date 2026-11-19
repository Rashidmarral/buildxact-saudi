<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use Database\Seeders\SaedEstMaterialCreditSubcontractPaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please add these subbase and asphalt as paid cash for subcontractor
 * ... this is correct not wrong i need it exactly this way" — the
 * operator's explicit instruction to count the Receiving Payments
 * Report's Sub Base (73,000.00) and Asphalt (107,352.20) category
 * figures as real applied payments against the SAED EST contract, on
 * top of the 161,860.00 already tracked, so the contract's own total
 * reads 342,212.20 everywhere — not just in the printed category table.
 */
class SaedEstMaterialCreditSubcontractPaymentSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithRealSubcontract(float $alreadyPaid = 161860.00): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => 'Subbase, Base Course and Asphalt work', 'contract_value' => 373650.00,
            'retention_percent' => 5, 'status' => 'active', 'start_date' => '2026-09-25',
        ]);
        foreach ([
            ['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730.00, 'requires_handover_approval' => false],
            ['sequence' => 2, 'name' => 'Milestone 2', 'percentage' => 30, 'amount' => 112095.00, 'requires_handover_approval' => false],
            ['sequence' => 3, 'name' => 'Milestone 3', 'percentage' => 30, 'amount' => 112095.00, 'requires_handover_approval' => false],
            ['sequence' => 4, 'name' => 'Milestone 4', 'percentage' => 15, 'amount' => 56047.50, 'requires_handover_approval' => false],
            ['sequence' => 5, 'name' => 'Final (after handover & approval)', 'percentage' => 5, 'amount' => 18682.50, 'requires_handover_approval' => true],
        ] as $m) {
            $subcontract->milestones()->create($m);
        }

        if ($alreadyPaid > 0) {
            $subcontract->applyPaymentAcrossMilestones($alreadyPaid, ['paid_at' => '2026-10-28', 'reference' => 'Real cash paid to Shahid to date']);
        }

        return [$company, $subcontract];
    }

    public function test_it_errors_cleanly_when_no_matching_subcontract_exists(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();

        $this->assertSame(0, SubcontractPayment::count());
    }

    public function test_it_records_sub_base_and_asphalt_as_real_payments_totalling_342212_20(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();

        $this->assertEqualsWithDelta(342212.20, $subcontract->fresh()->totalPaid(), 0.01);
        $this->assertTrue($subcontract->payments()->where('reference', 'like', 'Sub Base material%')->exists());
        $this->assertTrue($subcontract->payments()->where('reference', 'like', 'Asphalt material%')->exists());
    }

    public function test_the_waterfall_correctly_finishes_milestone_2_then_fills_3_then_partially_fills_4(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();

        $subcontract->refresh();
        $milestones = $subcontract->milestones()->orderBy('sequence')->get()->keyBy('name');

        $this->assertEqualsWithDelta(0.00, $milestones['Advance']->balance(), 0.01);
        $this->assertEqualsWithDelta(0.00, $milestones['Milestone 2']->balance(), 0.01);
        $this->assertEqualsWithDelta(0.00, $milestones['Milestone 3']->balance(), 0.01);
        $this->assertEqualsWithDelta(12755.30, $milestones['Milestone 4']->balance(), 0.01);
        // Final requires handover approval and was never due — untouched.
        $this->assertEqualsWithDelta(18682.50, $milestones['Final (after handover & approval)']->balance(), 0.01);
    }

    public function test_retention_and_balance_after_retention_reflect_the_new_total(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();

        $subcontract->refresh();
        $this->assertEqualsWithDelta(31437.80, $subcontract->balanceBeforeRetention(), 0.01);
        $this->assertEqualsWithDelta(18682.50, $subcontract->retentionAmount(), 0.01);
        $this->assertEqualsWithDelta(12755.30, $subcontract->balanceAfterRetention(), 0.01);
    }

    public function test_reseeding_does_not_duplicate_the_payments(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();
        (new SaedEstMaterialCreditSubcontractPaymentSeeder)->run();

        $this->assertEqualsWithDelta(342212.20, $subcontract->fresh()->totalPaid(), 0.01);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractBoqItem;
use App\Models\Supplier;
use Database\Seeders\SaedEstSubcontractBoqCorrectionSeeder;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "shahud also do 1200 subabse and 1200 base course as extra" — the old
 * single blended "Sub base+ABC" BOQ line (1,200 units) is wrong: Shahid
 * did 1,200 extra m² of EACH, matching the client's own revised PO
 * (11,200 m² Subbase, 11,200 m² Base Course). The money was already
 * right (SAR 24,000 either way) — this only fixes the BOQ structure.
 */
class SaedEstSubcontractBoqCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithRealSubcontract(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => 'Subbase, Base Course and Asphalt work', 'contract_value' => 373650.00,
            'retention_percent' => 5, 'status' => 'active', 'start_date' => '2026-09-25',
        ]);
        foreach ([
            ['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730.00],
            ['sequence' => 2, 'name' => 'Milestone 2', 'percentage' => 30, 'amount' => 112095.00],
            ['sequence' => 3, 'name' => 'Milestone 3', 'percentage' => 30, 'amount' => 112095.00],
            ['sequence' => 4, 'name' => 'Milestone 4', 'percentage' => 15, 'amount' => 56047.50],
            ['sequence' => 5, 'name' => 'Final', 'percentage' => 5, 'amount' => 18682.50, 'requires_handover_approval' => true],
        ] as $m) {
            $subcontract->milestones()->create($m);
        }

        return [$company, $subcontract];
    }

    public function test_it_errors_cleanly_when_the_subcontract_does_not_exist_yet(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new SaedEstSubcontractBoqCorrectionSeeder)->run();

        $this->assertSame(0, SubcontractBoqItem::count());
    }

    public function test_it_does_nothing_when_the_boq_was_already_seeded_correctly(): void
    {
        [, $subcontract] = $this->seedCompanyWithRealSubcontract();
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $before = $subcontract->boqItems()->orderBy('sort_order')->get()->toArray();

        (new SaedEstSubcontractBoqCorrectionSeeder)->run();

        $after = $subcontract->boqItems()->orderBy('sort_order')->get()->toArray();
        $this->assertSame($before, $after);
        $this->assertCount(3, $after);
        $this->assertNull($subcontract->boqItems()->where('description', 'Sub base+ABC')->first());
    }

    /**
     * Simulates the real production database: the old seeder already
     * ran once and left the 4-line "Sub base+ABC" structure behind
     * before this fix existed.
     */
    public function test_it_corrects_an_old_blended_line_into_two_separate_11200_lines(): void
    {
        [, $subcontract] = $this->seedCompanyWithRealSubcontract();
        foreach ([
            ['description' => 'Sub base', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 6.00, 'amount' => 60000.00, 'sort_order' => 0],
            ['description' => 'Base Course', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 14.00, 'amount' => 140000.00, 'sort_order' => 1],
            ['description' => 'Asphalt', 'quantity' => 4100, 'unit' => 'm2', 'rate' => 36.50, 'amount' => 149650.00, 'sort_order' => 2],
            ['description' => 'Sub base+ABC', 'quantity' => 1200, 'unit' => 'm2', 'rate' => 20.00, 'amount' => 24000.00, 'sort_order' => 3],
        ] as $item) {
            $subcontract->boqItems()->create($item);
        }

        (new SaedEstSubcontractBoqCorrectionSeeder)->run();

        $items = $subcontract->boqItems()->orderBy('sort_order')->get()->keyBy('description');
        $this->assertCount(3, $items);
        $this->assertNull($items->get('Sub base+ABC'));

        $this->assertEqualsWithDelta(11200, (float) $items['Sub base']->quantity, 0.001);
        $this->assertEqualsWithDelta(67200.00, (float) $items['Sub base']->amount, 0.01);

        $this->assertEqualsWithDelta(11200, (float) $items['Base Course']->quantity, 0.001);
        $this->assertEqualsWithDelta(156800.00, (float) $items['Base Course']->amount, 0.01);

        // Asphalt untouched.
        $this->assertEqualsWithDelta(4100, (float) $items['Asphalt']->quantity, 0.001);
        $this->assertEqualsWithDelta(149650.00, (float) $items['Asphalt']->amount, 0.01);

        // The money is exactly unchanged — contract_value, milestones,
        // everything built on top of it stays correct with zero further action.
        $this->assertEqualsWithDelta(373650.00, (float) $subcontract->boqItems()->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(373650.00, (float) $subcontract->fresh()->contract_value, 0.01);
    }

    public function test_reseeding_does_not_change_it_twice(): void
    {
        [, $subcontract] = $this->seedCompanyWithRealSubcontract();
        foreach ([
            ['description' => 'Sub base', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 6.00, 'amount' => 60000.00, 'sort_order' => 0],
            ['description' => 'Base Course', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 14.00, 'amount' => 140000.00, 'sort_order' => 1],
            ['description' => 'Asphalt', 'quantity' => 4100, 'unit' => 'm2', 'rate' => 36.50, 'amount' => 149650.00, 'sort_order' => 2],
            ['description' => 'Sub base+ABC', 'quantity' => 1200, 'unit' => 'm2', 'rate' => 20.00, 'amount' => 24000.00, 'sort_order' => 3],
        ] as $item) {
            $subcontract->boqItems()->create($item);
        }

        (new SaedEstSubcontractBoqCorrectionSeeder)->run();
        $afterFirst = $subcontract->boqItems()->orderBy('sort_order')->get()->toArray();

        (new SaedEstSubcontractBoqCorrectionSeeder)->run();
        $afterSecond = $subcontract->boqItems()->orderBy('sort_order')->get()->toArray();

        $this->assertSame($afterFirst, $afterSecond);
    }
}

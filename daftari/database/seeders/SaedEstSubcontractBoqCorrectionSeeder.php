<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * "shahud also do 1200 subabse and 1200 base course as extra" — the
 * client PO's own quantity revision (Subbase and Base Course each up
 * from 10,000 to 11,200 m², confirmed against the client's real
 * invoices INV-00001/00002/00003, which together bill exactly 11,200 m²
 * of each) carries straight through to Shahid's own scope: he did
 * 1,200 extra m² of Subbase AND 1,200 extra m² of Base Course (2,400 m²
 * total), not the single blended 1,200-unit line
 * SaedEstSubcontractorPaymentsSeeder originally seeded.
 *
 * The money was already right either way — 1,200 × SAR 6 (Subbase) +
 * 1,200 × SAR 14 (Base Course) = SAR 24,000, exactly what the old
 * single "Sub base+ABC" line already totalled — so this changes
 * nothing about what's owed to Shahid (contract_value stays SAR
 * 373,650.00 and every milestone, payment, Bill and Purchase Return
 * built on top of it is unaffected). It only restructures the BOQ
 * itself so "Sub base" and "Base Course" correctly read 11,200 m² each
 * — matching the client-side PO exactly — instead of a separate,
 * confusing "Sub base+ABC" line.
 *
 * Run it yourself, after taking a database backup, any time after
 * SaedEstSubcontractorPaymentsSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstSubcontractBoqCorrectionSeeder"
 *
 * Idempotent: does nothing if the old "Sub base+ABC" line isn't there
 * (either never seeded, or already corrected by a previous run).
 */
class SaedEstSubcontractBoqCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $project = Project::where('company_id', $company->id)->where('code', 'PROJ_00001')->first();
        $supplier = Supplier::where('company_id', $company->id)->where('cr_number', '4031259996')->first();
        $subcontract = $project && $supplier
            ? Subcontract::where('company_id', $company->id)->where('project_id', $project->id)->where('supplier_id', $supplier->id)->first()
            : null;

        if (! $subcontract) {
            $this->command?->error('The Jumum Yard project, SAED EST supplier, or its Subcontract was not found — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        $extraLine = $subcontract->boqItems()->where('description', 'Sub base+ABC')->first();

        if (! $extraLine) {
            $this->command?->info('BOQ already corrected (or never had the old "Sub base+ABC" line) — skipping.');

            return;
        }

        $subBase = $subcontract->boqItems()->where('description', 'Sub base')->first();
        $baseCourse = $subcontract->boqItems()->where('description', 'Base Course')->first();

        if (! $subBase || ! $baseCourse) {
            $this->command?->error('Expected "Sub base" and "Base Course" BOQ lines were not found — aborting without changing anything.');

            return;
        }

        $subBase->update(['quantity' => 11200, 'amount' => 11200 * (float) $subBase->rate]);
        $baseCourse->update(['quantity' => 11200, 'amount' => 11200 * (float) $baseCourse->rate]);
        $extraLine->delete();

        $newTotal = round((float) $subcontract->boqItems()->sum('amount'), 2);

        $this->command?->info('Sub base and Base Course both corrected to 11,200 m². BOQ total: SAR '.number_format($newTotal, 2).' (unchanged — contract_value stays SAR '.number_format((float) $subcontract->contract_value, 2).').');
    }
}

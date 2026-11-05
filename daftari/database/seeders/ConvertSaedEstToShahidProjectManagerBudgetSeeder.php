<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * "The PM budget should replace the subcontractor record" — the
 * operator confirmed Shahid is a project manager going forward, not a
 * subcontractor, and that this should be ONE record, not two. Rather
 * than creating a second, empty "Project Manager Budget" and trying
 * to copy 373,650 worth of real history (BOQ, milestones, every real
 * payment, the Shahid/Sub Base/Asphalt report categories, the
 * material-credit payments) across to it — real risk of losing or
 * duplicating something — this converts the real SAED EST subcontract
 * row IN PLACE: party_type becomes 'employee', employee_id points at
 * Shahid, supplier_id is cleared. Every related row (milestones,
 * payments, boqItems, reportCategories, works) belongs to this same
 * subcontract_id and is completely untouched by the conversion — nothing
 * is copied or recreated, so nothing can go missing or get duplicated.
 *
 * The real numbers this carries over unchanged: contract_value
 * 373,650.00, the 20/30/30/15/5% milestone schedule, 5% retention,
 * every linked voucher, and the Shahid/Sub Base (73,000.00)/Asphalt
 * (107,352.20) report categories and material-credit payments from
 * SaedEstMaterialCreditSubcontractPaymentSeeder.
 *
 * After this runs, the record only opens under Project Manager Budgets
 * (/app/project-manager-budgets/{id}) — the old Subcontractor Payments
 * URL for the same id now 404s (ensureInScope() checks party_type),
 * same as any other employee-party record.
 *
 * If a separate, empty "Shahid" Project Manager Budget already exists
 * for this project (e.g. created by hand while following a walkthrough
 * before this conversion ran) — zero payments recorded on it — it's
 * deleted as a redundant empty shell. If it has real payments on it,
 * each one is checked against SAED EST's own payments: a payment whose
 * payment_voucher_id is ALSO already linked to the real SAED EST
 * subcontract is a confirmed duplicate (the same voucher got linked to
 * both records while trying out the Project Manager Budgets feature)
 * and is removed from the duplicate — never from SAED EST, where the
 * real link stays. Once every payment on the duplicate is accounted
 * for this way, it's deleted as an empty shell and the conversion
 * proceeds. Any payment that does NOT match a voucher already on SAED
 * EST is left alone and the whole conversion is refused, rather than
 * guess it's safe to discard; resolve that by hand first.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\ConvertSaedEstToShahidProjectManagerBudgetSeeder"
 *
 * Idempotent: does nothing once the SAED EST record is already
 * party_type='employee'.
 */
class ConvertSaedEstToShahidProjectManagerBudgetSeeder extends Seeder
{
    private const SHAHID_DEFAULTS = [
        'full_name' => 'Shahid',
        'full_name_ar' => 'شا هد',
        'nationality' => 'Pakistani',
        'gender' => 'male',
        'address' => 'Makkah',
        'job_title' => 'Project Manager',
        'department' => 'Road Construction',
        'employment_type' => 'contract',
        'hire_date' => '2026-10-25',
        'basic_salary' => 500,
        'housing_allowance' => 200,
        'is_saudi' => false,
    ];

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

        if (! $project || ! $supplier || ! $subcontract) {
            $this->command?->error('The Jumum Yard project, SAED EST supplier, or its Subcontract record was not found — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        if ($subcontract->isEmployeeParty()) {
            $this->command?->info('Already converted to a Project Manager Budget — skipping.');

            return;
        }

        $employee = Employee::firstOrCreate(
            ['company_id' => $company->id, 'full_name' => self::SHAHID_DEFAULTS['full_name']],
            ['employee_number' => $company->nextEmployeeNumber(), ...self::SHAHID_DEFAULTS]
        );

        $duplicate = Subcontract::where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('party_type', 'employee')
            ->where('employee_id', $employee->id)
            ->where('id', '!=', $subcontract->id)
            ->first();

        if ($duplicate && $duplicate->payments()->exists()) {
            $saedEstVoucherIds = $subcontract->payments()->whereNotNull('payment_voucher_id')->pluck('payment_voucher_id');

            $nonDuplicatePayments = $duplicate->payments()
                ->where(function ($query) use ($saedEstVoucherIds) {
                    $query->whereNull('payment_voucher_id')->orWhereNotIn('payment_voucher_id', $saedEstVoucherIds);
                })
                ->get();

            if ($nonDuplicatePayments->isNotEmpty()) {
                $this->command?->error("Subcontract #{$duplicate->id} is a separate employee-party record for Shahid on this project with real payments already on it — refusing to guess which record is authoritative. Resolve this by hand, then re-run.");

                return;
            }

            $this->command?->info("Removing duplicate payment vouchers from Project Manager Budget #{$duplicate->id} already linked to SAED EST.");
            $duplicate->payments()->delete();
        }

        if ($duplicate) {
            $this->command?->info("Deleting empty duplicate Project Manager Budget #{$duplicate->id} (no payments recorded on it).");
            $duplicate->milestones()->delete();
            $duplicate->boqItems()->delete();
            $duplicate->works()->delete();
            $duplicate->reportCategories()->delete();
            $duplicate->delete();
        }

        $subcontract->update([
            'party_type' => 'employee',
            'employee_id' => $employee->id,
            'supplier_id' => null,
        ]);

        $this->command?->info("Subcontract #{$subcontract->id} converted to a Project Manager Budget for {$employee->full_name} (SAR ".number_format((float) $subcontract->contract_value, 2).').');
    }
}

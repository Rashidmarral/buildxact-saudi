<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * "please add these subbase and asphalt as paid cash for subcontractor
 * ... this is correct not wrong i need it exactly this way" — the
 * operator confirmed the Receiving Payments Report's "Sub Base"
 * (146 trips × SAR 500 = SAR 73,000.00) and "Asphalt" (646.70 ton ×
 * SAR 166 = SAR 107,352.20) category figures are real value delivered
 * to Shahid/SAED EST, on top of the SAR 161,860.00 already tracked as
 * real cash, and should count as applied against the SAR 373,650.00
 * contract the same way a cash payment does.
 *
 * SaedEstSubcontractorPaymentsSeeder's seedReportCategories() created
 * these two as "measured" report categories purely for the printed
 * breakdown — their figure was deliberately never summed into any
 * contract total. This seeder is the operator's explicit reversal of
 * that: it records the exact same two figures as two real, manual
 * SubcontractPayment entries (the same mechanism as "Add a manual
 * payment record" in the UI), applied through the identical milestone
 * waterfall as every other payment — so Subcontract::totalPaid(), the
 * milestone schedule, retention, and "balance payable after
 * retention" all genuinely total SAR 342,212.20 applied, not just the
 * printed category table.
 *
 * KNOWN, FLAGGED TRADE-OFF (the operator chose this explicitly over
 * the alternative of reconciling it): the SAR 107,352.20 Asphalt
 * figure is the same real Dar Al Manajim invoice that
 * SaedEstMaterialSupplierCorrectionSeeder already credited against the
 * SAED EST Bill's balance via a Purchase Return, in the Bills module.
 * After this seeder runs, the Bills module and this Subcontract
 * tracker both carry a credit for that same real invoice, through two
 * independent paths that don't know about each other — so "how much
 * is still owed to Shahid" will differ between the Bill's own
 * balanceDue() and this Subcontract's balanceAfterRetention(). The
 * Subcontract module's own figures (what drives the Receiving
 * Payments Report and this dashboard) are the ones meant to be
 * authoritative for "what's left to pay Shahid" — same note already
 * carried in SaedEstMaterialSupplierCorrectionSeeder's own docblock.
 * The SAR 73,000.00 Sub Base figure has no matching real Bill at all
 * (the real Al Jazeera invoice behind the same base-course material is
 * SAR 27,043.48, not SAR 73,000.00), so it carries no such conflict.
 *
 * Run it yourself, after taking a database backup, once
 * SaedEstSubcontractorPaymentsSeeder has already run (it creates the
 * report categories and the real subcontract this seeder posts
 * against):
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstMaterialCreditSubcontractPaymentSeeder"
 *
 * Idempotent: does nothing for a reference that was already recorded.
 */
class SaedEstMaterialCreditSubcontractPaymentSeeder extends Seeder
{
    private const CREDITS = [
        [
            'reference' => 'Sub Base material supplied directly — 146 trips × SAR 500.00 (Ashraf / Al Jazeera, base course)',
            'amount' => 73000.00,
            'paid_at' => '2026-10-10',
        ],
        [
            'reference' => 'Asphalt material supplied directly — 646.70 ton × SAR 166.00 (Dar Al Manajim / Anajim, invoice CRED-2568)',
            'amount' => 107352.20,
            'paid_at' => '2026-10-20',
        ],
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

        if (! $subcontract) {
            $this->command?->error('No Subcontract found for SAED EST on this project — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        foreach (self::CREDITS as $credit) {
            if ($subcontract->payments()->where('reference', $credit['reference'])->exists()) {
                $this->command?->info('Already recorded — skipping: '.$credit['reference']);

                continue;
            }

            $subcontract->applyPaymentAcrossMilestones($credit['amount'], [
                'paid_at' => $credit['paid_at'],
                'reference' => $credit['reference'],
            ]);

            $this->command?->info('Recorded SAR '.number_format($credit['amount'], 2).' — '.$credit['reference']);
        }

        $this->command?->info('Subcontract total applied: SAR '.number_format($subcontract->totalPaid(), 2).' | Balance after retention: SAR '.number_format($subcontract->balanceAfterRetention(), 2));
    }
}

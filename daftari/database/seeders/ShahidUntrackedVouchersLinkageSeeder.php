<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 11 #10: PV-00036, PV-00037, PV-00038 and
 * PV-00039 (SAR 18,560.00 total) are real, already-posted vouchers
 * against SAED EST's bill (each already carries a bill_id/bill_payment_id,
 * so the cash and the AP reduction are already correct) — they were
 * simply never linked into Shahid's Subcontract milestone waterfall the
 * way every other real voucher was by SaedEstSubcontractorPaymentsSeeder
 * and ShahidTransferOct29Seeder. This adds only the missing
 * SubcontractPayment rows; it never creates a voucher and never posts
 * any journal entry.
 *
 * Processed oldest business date first (then voucher number), waterfalled
 * across whichever milestone is first still short — the same
 * SubcontractController::allocateAcrossMilestones() logic every other
 * voucher here uses, run against the milestones' live balances so
 * earlier real vouchers are already accounted for.
 *
 * Run it yourself, after taking a database backup, once
 * SaedEstSubcontractorPaymentsSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\ShahidUntrackedVouchersLinkageSeeder"
 *
 * Idempotent: skips any voucher that already has a SubcontractPayment row.
 */
class ShahidUntrackedVouchersLinkageSeeder extends Seeder
{
    private const VOUCHER_NUMBERS = ['PV-00038', 'PV-00039', 'PV-00036', 'PV-00037'];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $project = Project::where('company_id', $company->id)->where('code', 'PROJ_00001')->first();
        $supplier = Supplier::where('company_id', $company->id)->where('cr_number', '4031259996')->first();

        if (! $project || ! $supplier) {
            $this->command?->error('The Jumum Yard project or SAED EST supplier was not found — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        $subcontract = Subcontract::where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('supplier_id', $supplier->id)
            ->first();

        if (! $subcontract) {
            $this->command?->error('No Subcontract found for SAED EST on this project.');

            return;
        }

        $vouchers = PaymentVoucher::where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->whereIn('voucher_number', self::VOUCHER_NUMBERS)
            ->get()
            ->keyBy('voucher_number');

        $missing = array_diff(self::VOUCHER_NUMBERS, $vouchers->keys()->all());
        if (! empty($missing)) {
            $this->command?->error('Expected vouchers not found: '.implode(', ', $missing).' — nothing changed.');

            return;
        }

        $milestones = $subcontract->milestones()->orderBy('sequence')->get();

        DB::transaction(function () use ($company, $subcontract, $milestones, $vouchers) {
            foreach (self::VOUCHER_NUMBERS as $voucherNumber) {
                $voucher = $vouchers[$voucherNumber];

                if (SubcontractPayment::where('payment_voucher_id', $voucher->id)->exists()) {
                    continue;
                }

                $remaining = round((float) $voucher->amount, 2);
                $rows = [];

                foreach ($milestones as $milestone) {
                    if ($remaining <= 0.004) {
                        break;
                    }

                    $milestoneRemaining = $milestone->fresh()->balance();
                    if ($milestoneRemaining <= 0.004) {
                        continue;
                    }

                    $apply = round(min($remaining, $milestoneRemaining), 2);
                    $rows[] = ['milestone_id' => $milestone->id, 'amount' => $apply];
                    $remaining = round($remaining - $apply, 2);
                }

                if ($remaining > 0.004) {
                    $rows[] = ['milestone_id' => null, 'amount' => $remaining];
                }

                foreach ($rows as $i => $row) {
                    $subcontract->payments()->create([
                        'company_id' => $company->id,
                        'milestone_id' => $row['milestone_id'],
                        'payment_voucher_id' => $voucher->id,
                        'amount' => $row['amount'],
                        'gross_amount' => $i === 0 ? (float) $voucher->amount : 0,
                        'paid_at' => $voucher->date,
                        'method' => $voucher->method,
                        'reference' => $voucher->reference,
                    ]);
                }

                $this->command?->info("Linked {$voucherNumber} (SAR ".number_format((float) $voucher->amount, 2).') into the subcontract milestone schedule.');
            }
        });
    }
}

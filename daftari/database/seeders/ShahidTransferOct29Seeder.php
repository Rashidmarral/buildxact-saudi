<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "add more 10000 to shahid account trasfer today" — one new, real
 * SAR 10,000 bank transfer to Shahid against the SAED EST / Jumum Yard
 * subcontract (see SaedEstSubcontractorPaymentsSeeder for the earlier
 * 28-voucher backfill this follows). A one-off event, kept as its own
 * seeder rather than folded into that backfill, the same way
 * TawzeaPo003347Seeder is its own file for one real PO — each seeder
 * here represents one real thing that happened, not a recurring job.
 *
 * Posts through the normal voucher/GL path (a real new PaymentVoucher,
 * using the company's own next voucher number, then
 * LedgerPostingService) and links it into the Subcontract's milestone
 * waterfall exactly like SubcontractController::linkVoucher() would.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\ShahidTransferOct29Seeder"
 *
 * Idempotent: skipped entirely if a SAED EST voucher already exists for
 * today's date, SAR 10,000.00, mentioning "Shahid" in its reference —
 * re-running (or running this after you've already entered the same
 * transfer yourself through the UI) does not create a duplicate.
 */
class ShahidTransferOct29Seeder extends Seeder
{
    private const AMOUNT = 10000.00;

    private const REFERENCE = 'Payment to Shahid Sir for project works – مشروعساحةالجموم';

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

        $alreadyRecorded = PaymentVoucher::where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->where('amount', self::AMOUNT)
            ->whereDate('date', now()->toDateString())
            ->where('reference', 'like', '%Shahid%')
            ->exists();

        if ($alreadyRecorded) {
            $this->command?->info('A matching Shahid transfer for today already exists — nothing to do.');

            return;
        }

        $operating = BankAccount::where('company_id', $company->id)->where('name', 'SNB — Operating Account')->first();

        if (! $operating) {
            $this->command?->error('SNB — Operating Account bank account not found.');

            return;
        }

        DB::transaction(function () use ($company, $project, $supplier, $subcontract, $operating) {
            $voucher = PaymentVoucher::create([
                'company_id' => $company->id,
                'bank_account_id' => $operating->id,
                'project_id' => $project->id,
                'party_type' => 'supplier',
                'supplier_id' => $supplier->id,
                'payee_name' => 'SAED EST',
                'party_name_ar' => 'مؤسسة سائد للمقاولات',
                'party_phone' => '966536468994',
                'party_address' => 'Makkah, Al Hamra Umm Al Jud, 24225, Saudi Arabia',
                'voucher_number' => $company->nextPaymentVoucherNumber(),
                'date' => now()->toDateString(),
                'amount' => self::AMOUNT,
                'method' => 'bank_transfer',
                'reference' => self::REFERENCE,
                'notes' => self::REFERENCE,
                'status' => 'issued',
            ]);

            app(LedgerPostingService::class)->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
            $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format(self::AMOUNT, 2).' to Shahid.');

            $milestones = $subcontract->milestones()->orderBy('sequence')->get();
            $remaining = self::AMOUNT;
            $rows = [];

            foreach ($milestones as $milestone) {
                if ($remaining <= 0.004) {
                    break;
                }

                $milestoneRemaining = $milestone->balance();
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
                    'gross_amount' => $i === 0 ? self::AMOUNT : 0,
                    'paid_at' => $voucher->date,
                    'method' => $voucher->method,
                    'reference' => $voucher->reference,
                ]);
            }
        });
    }
}

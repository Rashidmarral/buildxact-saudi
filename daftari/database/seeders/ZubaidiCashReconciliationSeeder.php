<?php

namespace Database\Seeders;

use App\Models\Account;
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
 * "the 55k cash i will explain we have transfer the 25k first to
 * zubaidi account and this 25k used 19k payment voucher issued like
 * 15k and 4k to shahid remaining 6k 1k add to owner remaining 5k then
 * transfer 30k to zubaidi from company main snb account ,total 35k now
 * 10k issue payment voucher for shahid add more 25k to owner from this
 * we have 10k records in our app as well where it will be used i
 * think" — the operator's own corrected breakdown of the SAR 55,000
 * (SAR 25,000 + SAR 30,000) cash moved to pay Shahid in cash, flagged
 * by the audit as only partly accounted for.
 *
 * Two of the three real Shahid cash payments already exist and are
 * already linked into the Subcontract — PV-00017 (SAR 15,000, cash,
 * 2026-10-11) and PV-00025 (SAR 10,000, cash, 2026-10-15, "Payment to
 * Mr. Shahid at Jamum by Rashid") are both already seeded by
 * SaedEstSubcontractorPaymentsSeeder. That's the "10k records in our
 * app" the operator remembered — nothing to create for it.
 *
 * What was genuinely missing:
 *   - SAR 4,000 cash to Shahid (the rest of the first 25k's 19k-to-
 *     Shahid total) — a real voucher, linked into the Subcontract like
 *     every other real Shahid payment.
 *   - SAR 1,000 + SAR 25,000 = SAR 26,000 the owner personally drew
 *     from this same cash — not a business expense or a Shahid
 *     payment, so it's recorded as a Payment Voucher whose counterpart
 *     is the Owner's Drawings account (3100 — see
 *     OwnerPersonalExpenseReclassificationSeeder, which must run
 *     first to create it) instead of Accounts Payable, exactly the
 *     same way any voucher with an explicit counter_account_id works.
 *
 * Assumption flagged for the operator to correct if wrong: the first
 * 25k tranche (15k + 4k to Shahid, 1k to owner) is dated against
 * 2026-10-12 and posted through the SNB Operating account, matching
 * the real PV-00017's own account; the second 35k tranche (10k to
 * Shahid, 25k to owner) is dated 2026-10-15 and posted through Petty
 * Cash, matching the real PV-00025's own account. Which specific real
 * cash bucket (SNB vs. Petty Cash) actually held this money at each
 * point wasn't independently verifiable from the data available here.
 *
 * Run it yourself, after taking a database backup, once
 * OwnerPersonalExpenseReclassificationSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\ZubaidiCashReconciliationSeeder"
 *
 * Idempotent: does nothing if the SAR 4,000 Shahid voucher already
 * exists.
 */
class ZubaidiCashReconciliationSeeder extends Seeder
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
        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        $operating = BankAccount::where('company_id', $company->id)->where('name', 'SNB — Operating Account')->first();
        $pettyCash = BankAccount::where('company_id', $company->id)->where('name', 'Petty Cash')->first();

        if (! $project || ! $supplier || ! $subcontract) {
            $this->command?->error('The Jumum Yard project, SAED EST supplier, or its Subcontract was not found — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        if (! $drawings) {
            $this->command?->error("Owner's Drawings account (3100) not found — run OwnerPersonalExpenseReclassificationSeeder first.");

            return;
        }

        if (! $operating || ! $pettyCash) {
            $this->command?->error('SNB — Operating Account or Petty Cash bank account not found.');

            return;
        }

        $alreadyDone = PaymentVoucher::where('company_id', $company->id)
            ->where('amount', 4000.00)
            ->where('reference', 'like', '%Zubaidi cash — remainder of 19,000 to Shahid%')
            ->exists();

        if ($alreadyDone) {
            $this->command?->info('Zubaidi cash reconciliation already applied — skipping.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($company, $project, $supplier, $subcontract, $drawings, $operating, $pettyCash, $ledger) {
            $this->payShahid($company, $project, $subcontract, $operating, $ledger, 4000.00, '2026-10-12', 'Zubaidi cash — remainder of 19,000 to Shahid (15,000 already recorded as PV-00017)');

            $this->ownerDraw($company, $project, $operating, $drawings, $ledger, 1000.00, '2026-10-12', 'Owner personal draw — Zubaidi cash, first 25,000 tranche');
            $this->ownerDraw($company, $project, $pettyCash, $drawings, $ledger, 25000.00, '2026-10-15', 'Owner personal draw — Zubaidi cash, second 30,000 tranche');
        });

        $this->command?->info('Zubaidi cash reconciliation complete: SAR 4,000 to Shahid, SAR 26,000 owner drawings recorded.');
    }

    private function payShahid(Company $company, Project $project, Subcontract $subcontract, BankAccount $bank, LedgerPostingService $ledger, float $amount, string $date, string $reference): void
    {
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id,
            'bank_account_id' => $bank->id,
            'project_id' => $project->id,
            'party_type' => 'supplier',
            'supplier_id' => $subcontract->supplier_id,
            'payee_name' => 'SAED EST',
            'party_name_ar' => 'مؤسسة سائد للمقاولات',
            'party_phone' => '966536468994',
            'party_address' => 'Makkah, Al Hamra Umm Al Jud, 24225, Saudi Arabia',
            'voucher_number' => $this->nextFreeVoucherNumber($company),
            'date' => $date,
            'amount' => $amount,
            'method' => 'cash',
            'reference' => $reference,
            'notes' => $reference,
            'status' => 'issued',
        ]);

        $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
        $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format($amount, 2).' to Shahid.');

        $milestones = $subcontract->milestones()->orderBy('sequence')->get();
        $remaining = $amount;
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
                'gross_amount' => $i === 0 ? $amount : 0,
                'paid_at' => $voucher->date,
                'method' => $voucher->method,
                'reference' => $voucher->reference,
            ]);
        }
    }

    private function ownerDraw(Company $company, Project $project, BankAccount $bank, Account $drawings, LedgerPostingService $ledger, float $amount, string $date, string $reference): void
    {
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id,
            'bank_account_id' => $bank->id,
            'project_id' => $project->id,
            'party_type' => 'manual',
            'counter_account_id' => $drawings->id,
            'payee_name' => 'Owner (personal draw)',
            'voucher_number' => $this->nextFreeVoucherNumber($company),
            'date' => $date,
            'amount' => $amount,
            'method' => 'cash',
            'reference' => $reference,
            'notes' => $reference,
            'status' => 'issued',
        ]);

        $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
        $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format($amount, 2).' owner drawing.');
    }

    /**
     * The original 28-voucher backfill (SaedEstSubcontractorPaymentsSeeder)
     * creates vouchers with their real, already-used numbers directly,
     * without ever advancing company->next_payment_voucher_number — on
     * the real production database that's harmless (every one of those
     * real numbers was already issued through the normal UI, which does
     * advance the counter), but a fresh seed/test database starts the
     * counter at 1 and would collide. Skipping past anything already
     * taken keeps this safe in both cases.
     */
    private function nextFreeVoucherNumber(Company $company): string
    {
        do {
            $candidate = $company->nextPaymentVoucherNumber();
        } while (PaymentVoucher::where('company_id', $company->id)->where('voucher_number', $candidate)->exists());

        return $candidate;
    }
}

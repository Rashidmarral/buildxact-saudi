<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractMilestone;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The 28 real "SAED EST" payment vouchers from the uploaded Payment
 * Summary PDF for Dynamic Core Contracting Company (VAT 314526094900003
 * — see RealCompanySeeder), covering the Jumum Yard Project. SAED EST
 * is the umbrella supplier record every payment is booked against, but
 * the real money goes to several named sub-parties (see
 * GroupsVouchersByPayee's docblock) — this seeder also creates one
 * Subcontract per real sub-party (Shahid, Ashraf, Al Jazeera, Dar Al
 * Anajim/Asphalt — Machine Rental and the one unmatched SAAD RIMAN
 * voucher aren't subcontracted work with a schedule of their own, so
 * they're left as plain vouchers, already covered by the "Machine
 * Rental" quick filter on the Payment Vouchers page) and links each
 * matching voucher into it.
 *
 * Two corrections per the operator's own instructions, applied as the
 * *net* amount counted toward the contract/milestone (see
 * SubcontractPayment's gross_amount/amount split) while every voucher
 * keeps recording the real cash actually transferred:
 *   - "i have paid the same amount with tax but i am paying my
 *     supplier this amount without tax" — the three Dar Al Anajim
 *     asphalt vouchers total SAR 122,665.60 gross; net SAR 107,352.20
 *     applied, split proportionally since no single voucher was named.
 *   - Al Jazeera (SHAERIKAIH ALGAZAIERIH ALERIBAIH): each voucher's
 *     net = gross ÷ 1.15 (VAT-inclusive unwind), per the operator's
 *     confirmed choice.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstSubcontractorPaymentsSeeder"
 *
 * Idempotent: every voucher is firstOrCreate'd on (company_id,
 * voucher_number) — re-running never duplicates a voucher or re-posts
 * its GL entry — and every Subcontract/SubcontractPayment link is
 * firstOrCreate'd the same way, so this is safe to re-run after you've
 * started editing things in the UI.
 *
 * What you MUST still review afterwards — nothing here is authoritative
 * beyond what the PDF showed:
 *   - Each Subcontract is seeded with a single placeholder 100% "Full"
 *     milestone and a contract_value equal to the amount paid so far
 *     (the real contract value/BOQ/milestone schedule wasn't in the
 *     PDF). Edit these from Subcontractor Payments > (each contract) >
 *     Edit once you have the real figures.
 *   - The Asphalt group's proportional VAT split, if you know which
 *     specific voucher(s) actually carried the VAT difference.
 */
class SaedEstSubcontractorPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $project = Project::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Jumum Yard Project'],
            ['code' => 'JUMUM-YARD', 'status' => 'active', 'start_date' => '2026-09-28']
        );

        $supplier = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'SAED EST'],
            ['cr_number' => '4031259996']
        );

        $operating = BankAccount::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'SNB — Operating Account'],
            ['bank_name' => 'Saudi National Bank (SNB)', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]
        );

        $pettyCash = BankAccount::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Petty Cash'],
            ['type' => 'cash', 'currency' => 'SAR', 'is_active' => true]
        );

        $groups = $this->groups($operating, $pettyCash);

        foreach ($groups as $groupKey => $group) {
            $computedTotal = round(collect($group['vouchers'])->sum('amount'), 2);

            if (abs($computedTotal - $group['expected_total']) > 0.02) {
                $this->command?->error(sprintf(
                    '%s: voucher lines total %.2f but the PDF subtotal is %.2f — aborting this group without writing anything.',
                    $group['label'], $computedTotal, $group['expected_total']
                ));

                continue;
            }

            DB::transaction(function () use ($company, $project, $supplier, $group) {
                $this->seedGroup($company, $project, $supplier, $group);
            });
        }
    }

    private function seedGroup(Company $company, Project $project, Supplier $supplier, array $group): void
    {
        $subcontract = Subcontract::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'title' => $group['label']],
            ['contract_value' => $group['expected_total'], 'retention_percent' => 5, 'status' => 'active', 'start_date' => '2026-09-28']
        );

        $milestone = $subcontract->milestones()->first() ?? SubcontractMilestone::create([
            'subcontract_id' => $subcontract->id, 'sequence' => 1, 'name' => 'Full',
            'percentage' => 100, 'amount' => $subcontract->contract_value,
        ]);

        $netTotal = $group['net_total'] ?? $group['expected_total'];
        $grossTotal = $group['expected_total'];
        $ratio = $grossTotal > 0 ? $netTotal / $grossTotal : 1.0;
        $netAssigned = 0.0;
        $rows = $group['vouchers'];
        $lastIndex = count($rows) - 1;

        foreach ($rows as $i => $row) {
            $voucher = PaymentVoucher::firstOrCreate(
                ['company_id' => $company->id, 'voucher_number' => $row['voucher_number']],
                [
                    'bank_account_id' => $row['bank_account']->id,
                    'project_id' => $project->id,
                    'party_type' => 'supplier',
                    'supplier_id' => $supplier->id,
                    'payee_name' => $row['payee_name'],
                    'date' => $row['date'],
                    'amount' => $row['amount'],
                    'method' => $row['method'],
                    'reference' => $row['reference'],
                    'status' => 'issued',
                ]
            );

            if ($voucher->wasRecentlyCreated) {
                app(LedgerPostingService::class)->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
                $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format((float) $voucher->amount, 2).' to '.$row['payee_name'].'.');
            }

            if (SubcontractPayment::where('payment_voucher_id', $voucher->id)->exists()) {
                continue;
            }

            // Net is proportional to this voucher's share of the gross
            // group total, except the last row, which takes whatever is
            // left over so the group's net total lands exactly on
            // $netTotal despite per-voucher rounding.
            $net = $i === $lastIndex
                ? round($netTotal - $netAssigned, 2)
                : round((float) $row['amount'] * $ratio, 2);
            $netAssigned += $net;

            $subcontract->payments()->create([
                'company_id' => $company->id,
                'milestone_id' => $milestone->id,
                'payment_voucher_id' => $voucher->id,
                'amount' => $net,
                'gross_amount' => abs($net - (float) $row['amount']) > 0.004 ? (float) $row['amount'] : null,
                'paid_at' => $row['date'],
                'method' => $row['method'],
                'reference' => $row['reference'],
            ]);
        }
    }

    private function groups(BankAccount $operating, BankAccount $pettyCash): array
    {
        return [
            'shahid' => [
                'label' => 'Shahid',
                'expected_total' => 114800.00,
                'vouchers' => [
                    ['voucher_number' => 'PV-00002', 'date' => '2026-09-28', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروع ساحة الجموم', 'payee_name' => 'Shahid', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00005', 'date' => '2026-09-29', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Shahid Sir for project works – مشروع ساحة الجموم', 'payee_name' => 'Shahid', 'amount' => 29000.00],
                    ['voucher_number' => 'PV-00007', 'date' => '2026-10-01', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Shahid Sir for project works – مشروع ساحة الجموم', 'payee_name' => 'Shahid', 'amount' => 14500.00],
                    ['voucher_number' => 'PV-00008', 'date' => '2026-10-01', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروع ساحة الجموم', 'payee_name' => 'Shahid', 'amount' => 6500.00],
                    ['voucher_number' => 'PV-00009', 'date' => '2026-10-06', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'payee_name' => 'Shahid', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00010', 'date' => '2026-10-07', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'payee_name' => 'Shahid', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00012', 'date' => '2026-10-08', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'payee_name' => 'Shahid', 'amount' => 14000.00],
                    ['voucher_number' => 'PV-00017', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'cash', 'reference' => 'Payment to MUHAMMED SHAHID for project works', 'payee_name' => 'Muhammed Shahid', 'amount' => 15000.00],
                    ['voucher_number' => 'PV-00018', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'cash', 'reference' => 'Received 4500 (4000 Nasir 500 Khalid bhai 1000 + 800 Labortry Test khalid bhai se lye 500 more cash Total 6800 Total', 'payee_name' => 'Muhammed Shahid', 'amount' => 6800.00],
                    ['voucher_number' => 'PV-00025', 'date' => '2026-10-15', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Mr. Shahid at Jamum by Rashid', 'payee_name' => 'Shahid', 'amount' => 10000.00],
                    ['voucher_number' => 'PV-00028', 'date' => '2026-10-20', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروع ساحة الجموم in terms of MC1', 'payee_name' => 'Shahid', 'amount' => 4000.00],
                ],
            ],
            'ashraf' => [
                'label' => 'Ashraf',
                'expected_total' => 61700.00,
                'vouchers' => [
                    ['voucher_number' => 'PV-00006', 'date' => '2026-09-30', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Net cash paid to Ashraf in terms of trailer rent project works – مشروع ساحة الجموم', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00014', 'date' => '2026-10-10', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 9000.00],
                    ['voucher_number' => 'PV-00015', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 10000.00],
                    ['voucher_number' => 'PV-00020', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Muhammad Ashraf for project works', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 20000.00],
                    ['voucher_number' => 'PV-00023', 'date' => '2026-10-15', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00030', 'date' => '2026-10-22', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 5000.00],
                    ['voucher_number' => 'PV-00034', 'date' => '2026-10-26', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Transfer to Ashraf account in terms of Asphalt Trailer rent', 'payee_name' => 'Muhammed Ashraf Muhammed Akram', 'amount' => 7700.00],
                ],
            ],
            'al_jazeera' => [
                'label' => 'Al Jazeera',
                'expected_total' => 31100.00,
                // "remove 15% tax" confirmed as: 31,100 is VAT-inclusive,
                // net = 31,100 ÷ 1.15.
                'net_total' => round(31100.00 / 1.15, 2),
                'vouchers' => [
                    ['voucher_number' => 'PV-00013', 'date' => '2026-10-10', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works – مشروع ساحة الجموم', 'payee_name' => 'Shaerikaih Algazaierih Aleribaih', 'amount' => 10000.00],
                    ['voucher_number' => 'PV-00016', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'payee_name' => 'Shaerikaih Algazaierih Aleribaih', 'amount' => 10000.00],
                    ['voucher_number' => 'PV-00019', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'payee_name' => 'Shaerikaih Algazaierih Aleribaih', 'amount' => 7500.00],
                    ['voucher_number' => 'PV-00021', 'date' => '2026-10-13', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'payee_name' => 'Shaerikaih Algazaierih Aleribaih', 'amount' => 2000.00],
                    ['voucher_number' => 'PV-00022', 'date' => '2026-10-14', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'payee_name' => 'Shaerikaih Algazaierih Aleribaih', 'amount' => 1600.00],
                ],
            ],
            'asphalt' => [
                'label' => 'Asphalt (Dar Al Anajim)',
                'expected_total' => 122665.60,
                // "i have paid the same amount with tax but i am paying
                // my supplier this amount without tax" — 107,352.20 net,
                // split proportionally since no single voucher was named.
                'net_total' => 107352.20,
                'vouchers' => [
                    ['voucher_number' => 'PV-00029', 'date' => '2026-10-20', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND INDUSTRY CO Asphalt payment', 'payee_name' => 'Dar Al Anajim For Mining and Industry Co', 'amount' => 82965.60],
                    ['voucher_number' => 'PV-00032', 'date' => '2026-10-23', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND INDUSTRY CO Asphalt payment', 'payee_name' => 'Dar Al Anajim For Mining and Industry Co', 'amount' => 19800.00],
                    ['voucher_number' => 'PV-00033', 'date' => '2026-10-23', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND INDUSTRY CO Asphalt payment', 'payee_name' => 'Dar Al Anajim For Mining and Industry Co', 'amount' => 19900.00],
                ],
            ],
        ];
    }
}

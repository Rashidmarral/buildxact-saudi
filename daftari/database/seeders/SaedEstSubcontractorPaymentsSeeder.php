<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "this my db take payment data from this date description etc to add
 * it subcontractor payment my company id is 1 dynamic core contracting
 * company" — the operator's own database export confirmed the real
 * picture: all 28 SAED EST payment vouchers already exist for real
 * (company_id=1, supplier_id=4, project_id=3 — "مشروع ساحة الجموم"),
 * and the operator had already created the real Subcontract themselves
 * through the Subcontractor Payments UI — ONE contract, "Subbase, Base
 * Course and Asphalt work", SAR 373,650.00, 5% retention, the standard
 * 20/30/30/15/5% milestone schedule — covering all of it, not one
 * contract per named worker as an earlier, guessed version of this
 * seeder assumed. This version links every real voucher into that one
 * real contract instead, through the normal voucher/GL-posting path
 * (firstOrCreate + LedgerPostingService — on the operator's real
 * database every voucher already exists, so this step is always a
 * no-op there; it only creates anything on a fresh/test database).
 *
 * Processed in chronological order (then voucher number) so the
 * milestone waterfall ("the payments i have made clear them as
 * milestone total milestone paid move to next") fills the schedule the
 * same way it actually happened, each voucher split across milestones
 * via the exact SubcontractController::allocateAcrossMilestones() logic.
 *
 * Two VAT corrections per the operator's own confirmed instructions —
 * every voucher keeps its real (gross) amount; only the amount applied
 * to the contract differs:
 *   - Al Jazeera (SHAERIKAIH ALGAZAIERIH ALERIBAIH): net = gross ÷ 1.15
 *     per voucher.
 *   - Dar Al Anajim asphalt (3 vouchers, gross SAR 122,665.60): net
 *     SAR 107,352.20 total, split proportionally since no single
 *     voucher was named.
 * Every other voucher (Shahid, Ashraf, Saad Riman, machine rental) has
 * no VAT adjustment — net equals gross.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstSubcontractorPaymentsSeeder"
 *
 * Idempotent: every voucher is firstOrCreate'd on (company_id,
 * voucher_number), and every link is firstOrCreate'd on
 * payment_voucher_id (plus a sequence suffix for a split voucher) — see
 * seedLinks() — so re-running never duplicates a voucher, re-posts its
 * GL entry, or double-links a payment already recorded.
 */
class SaedEstSubcontractorPaymentsSeeder extends Seeder
{
    private const SAED_EST_PARTY = [
        'payee_name' => 'SAED EST',
        'party_name_ar' => 'مؤسسة سائد للمقاولات',
        'party_phone' => '966536468994',
        'party_address' => 'Makkah, Al Hamra Umm Al Jud, 24225, Saudi Arabia',
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $project = Project::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PROJ_00001'],
            ['name' => 'مشروع ساحة الجموم', 'status' => 'active']
        );

        $supplier = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'cr_number' => '4031259996'],
            ['name' => 'SAED EST', 'name_ar' => 'مؤسسة سائد للمقاولات', 'phone' => '966536468994']
        );

        $subcontract = Subcontract::where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('supplier_id', $supplier->id)
            ->first();

        if (! $subcontract) {
            $this->command?->error('No Subcontract found for SAED EST on this project — create it first from Subcontractor Payments (contract value 373,650.00, 5% retention, the standard milestone schedule), then re-run this seeder.');

            return;
        }

        $milestones = $subcontract->milestones()->orderBy('sequence')->get();

        if ($milestones->isEmpty()) {
            $this->command?->error('That Subcontract has no milestones yet — add its milestone schedule first, then re-run this seeder.');

            return;
        }

        $this->seedBoqItems($subcontract);

        $operating = BankAccount::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'SNB — Operating Account'],
            ['bank_name' => 'Saudi National Bank (SNB)', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]
        );

        $pettyCash = BankAccount::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Petty Cash'],
            ['type' => 'cash', 'currency' => 'SAR', 'is_active' => true]
        );

        $rows = $this->voucherRows($operating, $pettyCash);

        $asphaltGross = round(collect($rows)->where('group', 'asphalt')->sum('amount'), 2);
        if (abs($asphaltGross - 122665.60) > 0.02) {
            $this->command?->error(sprintf('Dar Al Anajim asphalt lines total %.2f, expected 122,665.60 — aborting without writing anything.', $asphaltGross));

            return;
        }

        DB::transaction(function () use ($company, $project, $supplier, $subcontract, $milestones, $rows) {
            $vouchers = [];

            foreach ($rows as $row) {
                $voucher = PaymentVoucher::firstOrCreate(
                    ['company_id' => $company->id, 'voucher_number' => $row['voucher_number']],
                    [
                        ...self::SAED_EST_PARTY,
                        'bank_account_id' => $row['bank_account']->id,
                        'project_id' => $project->id,
                        'party_type' => 'supplier',
                        'supplier_id' => $supplier->id,
                        'date' => $row['date'],
                        'amount' => $row['amount'],
                        'method' => $row['method'],
                        'reference' => $row['reference'],
                        'notes' => $row['notes'] ?? $row['reference'],
                        'status' => 'issued',
                    ]
                );

                if ($voucher->wasRecentlyCreated) {
                    app(LedgerPostingService::class)->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
                    $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format((float) $voucher->amount, 2).'.');
                }

                $vouchers[] = ['voucher' => $voucher, 'net' => $this->netAmount($row)];
            }

            $this->seedLinks($company, $subcontract, $milestones, $vouchers);
        });
    }

    /**
     * Links every voucher into the subcontract's milestone schedule in
     * the order it's given (the caller already sorts chronologically),
     * waterfalling each voucher's net amount across whichever milestone
     * is first still short — exactly SubcontractController's own
     * allocateAcrossMilestones() logic, re-run here against the
     * milestones' live balances so earlier rows in this same pass are
     * already accounted for.
     */
    private function seedLinks($company, Subcontract $subcontract, $milestones, array $vouchers): void
    {
        foreach ($vouchers as $entry) {
            $voucher = $entry['voucher'];

            if (SubcontractPayment::where('payment_voucher_id', $voucher->id)->exists()) {
                continue;
            }

            $remaining = round($entry['net'], 2);
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
        }
    }

    private function netAmount(array $row): float
    {
        return match ($row['group']) {
            // 31,100 gross ÷ 1.15 per voucher — the operator's confirmed VAT unwind.
            'al_jazeera' => round($row['amount'] / 1.15, 2),
            // 122,665.60 gross across 3 vouchers → 107,352.20 net, split
            // proportionally — no single voucher was named.
            'asphalt' => round($row['amount'] * (107352.20 / 122665.60), 2),
            default => $row['amount'],
        };
    }

    /**
     * @return array<int, array{voucher_number: string, date: string, bank_account: BankAccount, method: string, reference: string, amount: float, group: string}>
     */
    /**
     * The refined 4-line BOQ breakdown the operator shared — Sub base
     * 10,000 m² @ 6.00, Base Course 10,000 m² @ 14.00, Asphalt 4,100 m²
     * @ 36.50, Sub base+ABC 1,200 m² @ 20.00 — totalling the same SAR
     * 373,650.00 the contract/milestones already use, so this only adds
     * documentation behind that figure, it never changes it. Only seeded
     * once: if the operator has since edited the BOQ themselves in the
     * UI, re-running this must never overwrite that.
     */
    private function seedBoqItems(Subcontract $subcontract): void
    {
        if ($subcontract->boqItems()->exists()) {
            return;
        }

        foreach ([
            ['description' => 'Sub base', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 6.00, 'amount' => 60000.00],
            ['description' => 'Base Course', 'quantity' => 10000, 'unit' => 'm2', 'rate' => 14.00, 'amount' => 140000.00],
            ['description' => 'Asphalt', 'quantity' => 4100, 'unit' => 'm2', 'rate' => 36.50, 'amount' => 149650.00],
            ['description' => 'Sub base+ABC', 'quantity' => 1200, 'unit' => 'm2', 'rate' => 20.00, 'amount' => 24000.00],
        ] as $i => $item) {
            $subcontract->boqItems()->create([...$item, 'sort_order' => $i]);
        }
    }

    private function voucherRows(BankAccount $operating, BankAccount $pettyCash): array
    {
        return [
            ['voucher_number' => 'PV-00002', 'date' => '2026-09-28', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروعساحةالجموم', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00005', 'date' => '2026-09-29', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Shahid Sir for project works – مشروعساحةالجموم', 'amount' => 29000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00006', 'date' => '2026-09-30', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Net cash paid to Ashraf in terms of trailer rent  project works – مشروعساحةالجموم', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00007', 'date' => '2026-10-01', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Shahid Sir for project works – مشروعساحةالجموم', 'amount' => 14500.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00008', 'date' => '2026-10-01', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروعساحةالجموم', 'amount' => 6500.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00009', 'date' => '2026-10-06', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00010', 'date' => '2026-10-07', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00012', 'date' => '2026-10-08', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Mr. Shahid against Project Works – Al-Jumum Yard Project', 'amount' => 14000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00013', 'date' => '2026-10-10', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to  SHAERIKAIH ALGAZAIERIH ALERIBAIH  for project works – مشروعساحةالجموم', 'amount' => 10000.00, 'group' => 'al_jazeera'],
            ['voucher_number' => 'PV-00014', 'date' => '2026-10-10', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'amount' => 9000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00015', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'amount' => 10000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00016', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'amount' => 10000.00, 'group' => 'al_jazeera'],
            ['voucher_number' => 'PV-00017', 'date' => '2026-10-11', 'bank_account' => $operating, 'method' => 'cash', 'reference' => 'Payment to MUHAMMED SHAHID for project works', 'amount' => 15000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00018', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'cash', 'reference' => 'Received 4500 (4000 Nasir 500 Khalid bhai  1000 + 800  Labortry Test khalid bhai se lye  500 more cash Total  6800 Total', 'notes' => 'Payment to MUHAMMED SHAHID for project works', 'amount' => 6800.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00019', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'amount' => 7500.00, 'group' => 'al_jazeera'],
            ['voucher_number' => 'PV-00020', 'date' => '2026-10-12', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Muhammad Ashraf for project works', 'amount' => 20000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00021', 'date' => '2026-10-13', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'amount' => 2000.00, 'group' => 'al_jazeera'],
            ['voucher_number' => 'PV-00022', 'date' => '2026-10-14', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SHAERIKAIH ALGAZAIERIH ALERIBAIH for project works', 'amount' => 1600.00, 'group' => 'al_jazeera'],
            ['voucher_number' => 'PV-00023', 'date' => '2026-10-15', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00025', 'date' => '2026-10-15', 'bank_account' => $pettyCash, 'method' => 'cash', 'reference' => 'Payment to Mr. Shahid at Jamum by Rashid', 'amount' => 10000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00028', 'date' => '2026-10-20', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for project works – مشروعساحةالجموم in terms of MC1', 'amount' => 4000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00029', 'date' => '2026-10-20', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND USTRY CO Asphalt payment', 'amount' => 82965.60, 'group' => 'asphalt'],
            ['voucher_number' => 'PV-00030', 'date' => '2026-10-22', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to MUHAMMED ASHRAF MUHAMMED AKRAM for project works', 'amount' => 5000.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00031', 'date' => '2026-10-22', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Payment to SAAD RIMAN MUTLAQ ALDAWSARI for project works – مشروعساحةالجموم', 'amount' => 10200.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00032', 'date' => '2026-10-23', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND USTRY CO Asphalt payment', 'amount' => 19800.00, 'group' => 'asphalt'],
            ['voucher_number' => 'PV-00033', 'date' => '2026-10-23', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'DAR AL ANAJIM FOR MINING AND USTRY CO Asphalt payment', 'amount' => 19900.00, 'group' => 'asphalt'],
            ['voucher_number' => 'PV-00035', 'date' => '2026-10-25', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Machine Rent and Lobed charges', 'amount' => 11600.00, 'group' => 'plain'],
            ['voucher_number' => 'PV-00034', 'date' => '2026-10-26', 'bank_account' => $operating, 'method' => 'bank_transfer', 'reference' => 'Transfer to Ashraf account in terms of Asphalt Trailer rent', 'amount' => 7700.00, 'group' => 'plain'],
        ];
    }
}

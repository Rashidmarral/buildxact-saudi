<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "our main supplier or subcontractor is Shahid Saed and we have agreed
 * prices with him without vat so what we have do we have purchased base
 * course from aljazera with vat and provide it to shahid and less his
 * balance of the total from aljazeera decreasing the vat amount the same
 * we have do for asphalt like dar al manajin give us asphalt with vat
 * 15% and the same amount we have add in balance to shahid supplier but
 * decreasing the vat" — Al Jazeera and Dar Al Anajim are NOT part of the
 * Shahid/SAED EST subcontract at all. They are two separate, real,
 * VAT-registered material suppliers Dynamic Core paid directly; the
 * material was then treated as supplied to Shahid, crediting his
 * VAT-exclusive contract balance at the NET (ex-VAT) cost only — the VAT
 * itself is Dynamic Core's own input VAT to reclaim, not Shahid's concern.
 *
 * SaedEstSubcontractorBillSeeder's first pass approximated this by
 * settling those 8 vouchers against the SAED EST bill at their net
 * figure instead of gross — a reasonable interim fix, but it left two
 * real problems: (1) no VAT-registered Bill ever existed for Al Jazeera
 * or Dar Al Anajim, so ~SAR 20,056.38 of real, recoverable input VAT was
 * never captured for the VAT return; (2) the full SAR 373,650.00 SAED
 * EST accrual still expensed this same material cost a second time, on
 * top of whatever these new bills would expense.
 *
 * This seeder completes the correction:
 *   1. Creates the two real suppliers and a real, 15%-VAT Bill for each,
 *      fully settled by the SAME 8 real vouchers moved off the SAED EST
 *      bill (no new cash — it's the exact same real payments, now
 *      correctly attributed to who was actually paid).
 *   2. Issues a Purchase Return against the SAED EST bill for the
 *      combined net cost (SAR 133,709.22), crediting Shahid's balance
 *      and reversing that same amount of the original Expense accrual —
 *      so the material is expensed exactly once, not twice.
 *
 * Net financial-statement effect: zero. Every riyal of real cash still
 * moves exactly as it did before (see the bank/cash invariant test).
 * Total Operating Expense recognized across all three suppliers combined
 * is still exactly SAR 373,650.00. The only real change is where that
 * expense and its VAT land: SAR 133,709.22 of it (plus SAR 20,056.38 of
 * input VAT that was previously missed entirely) now sits correctly on
 * Al Jazeera's and Dar Al Anajim's own bills instead of buried inside
 * SAED EST's.
 *
 * One figure changed from an earlier message: Dar Al Anajim's net cost
 * was previously approximated as SAR 107,352.20 (a rough proportional
 * estimate). A real VAT-registered supplier's bill has to tie to clean
 * 15% math, so this uses SAR 122,665.60 ÷ 1.15 = SAR 106,665.74 net /
 * SAR 15,999.86 VAT instead — SAR 686.46 different from the earlier
 * figure. Flag this to the operator before relying on it for the VAT
 * return; it's a judgment call made for internal consistency, not a
 * confirmed instruction.
 *
 * Known, pre-existing limitation NOT fixed here (out of scope — it's how
 * every bill with a purchase return behaves in this codebase, not
 * something specific to this one): Bill::balanceDue() never nets out
 * issued Purchase Returns, only amount_paid. So after this runs, the
 * SAED EST bill's own balanceDue() will overstate what's really left to
 * settle by the full SAR 133,709.22 credited — the Subcontract module's
 * own totalPaid()/balanceAfterRetention() (unaffected by this seeder,
 * still net-adjusted correctly) remains the authoritative "what's left
 * to pay Shahid" figure, same as before.
 *
 * Run it yourself, after taking a database backup, once
 * SaedEstSubcontractorBillSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstMaterialSupplierCorrectionSeeder"
 *
 * Idempotent: does nothing if the Purchase Return against the SAED EST
 * bill already exists.
 */
class SaedEstMaterialSupplierCorrectionSeeder extends Seeder
{
    private const GROUPS = [
        'al_jazeera' => [
            'name' => 'SHAERIKAIH ALGAZAIERIH ALERIBAIH',
            'description' => 'Base course material supplied to Shahid — Jumum Yard Project',
            'voucher_numbers' => ['PV-00013', 'PV-00016', 'PV-00019', 'PV-00021', 'PV-00022'],
            'bill_date' => '2026-10-10',
            'subtotal' => 27043.48,
            'vat_total' => 4056.52,
            'total' => 31100.00,
        ],
        'dar_al_anajim' => [
            'name' => 'DAR AL ANAJIM FOR MINING AND INDUSTRY CO',
            'description' => 'Asphalt material supplied to Shahid — Jumum Yard Project',
            'voucher_numbers' => ['PV-00029', 'PV-00032', 'PV-00033'],
            'bill_date' => '2026-10-20',
            'subtotal' => 106665.74,
            'vat_total' => 15999.86,
            'total' => 122665.60,
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
        $saedSupplier = Supplier::where('company_id', $company->id)->where('cr_number', '4031259996')->first();
        $saedBill = $project && $saedSupplier
            ? Bill::where('company_id', $company->id)->where('supplier_id', $saedSupplier->id)->where('project_id', $project->id)->first()
            : null;

        if (! $project || ! $saedSupplier || ! $saedBill) {
            $this->command?->error('The Jumum Yard project, SAED EST supplier, or its Bill was not found — run SaedEstSubcontractorBillSeeder first.');

            return;
        }

        if (PurchaseReturn::where('company_id', $company->id)->where('bill_id', $saedBill->id)->exists()) {
            $this->command?->info('Material supplier correction already applied — skipping.');

            return;
        }

        DB::transaction(function () use ($company, $project, $saedBill) {
            $ledger = app(LedgerPostingService::class);

            foreach (self::GROUPS as $group) {
                $supplier = Supplier::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $group['name']],
                    ['type' => 'company', 'country' => 'Saudi Arabia']
                );

                $bill = $this->createAndPostMaterialBill($company, $project, $supplier, $group);
                $this->reassignVouchers($company, $ledger, $saedBill, $bill, $supplier, $group);
            }

            $this->issuePurchaseReturn($company, $saedBill);
        });

        $this->command?->info('SAED EST bill balance due (not net of the Purchase Return — see this seeder\'s docblock): SAR '.number_format($saedBill->fresh()->balanceDue(), 2));
    }

    private function createAndPostMaterialBill(Company $company, Project $project, Supplier $supplier, array $group): Bill
    {
        $bill = Bill::where('company_id', $company->id)->where('supplier_id', $supplier->id)->first();

        if ($bill) {
            return $bill;
        }

        $bill = Bill::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'branch_id' => $company->default_branch_id,
            'bill_number' => $company->nextBillNumber(),
            'supplier_reference' => $group['description'],
            'status' => 'draft',
            'bill_date' => $group['bill_date'],
            'currency' => $company->currency ?? 'SAR',
            'exchange_rate' => 1,
            'notes' => 'Purchased directly and supplied to subcontractor SAED EST — see Purchase Return against that bill.',
        ]);

        $line = new BillItem([
            'bill_id' => $bill->id,
            'description' => $group['description'],
            'quantity' => 1,
            'unit_price' => $group['subtotal'],
            'vat_rate' => 15,
            'sort_order' => 0,
        ]);
        $line->recalculate();
        $line->save();

        $bill->recalculateTotals();
        $bill->update(['status' => 'posted']);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh('items.item'));

        $this->command?->info("Bill {$bill->bill_number} created and posted for {$supplier->name} — SAR ".number_format((float) $bill->total, 2).' (SAR '.number_format((float) $bill->vat_total, 2).' input VAT).');

        return $bill;
    }

    private function reassignVouchers(Company $company, LedgerPostingService $ledger, Bill $saedBill, Bill $materialBill, Supplier $supplier, array $group): void
    {
        $vouchers = PaymentVoucher::where('company_id', $company->id)
            ->whereIn('voucher_number', $group['voucher_numbers'])
            ->orderBy('date')
            ->get();

        foreach ($vouchers as $voucher) {
            if ($voucher->bill_id === $materialBill->id) {
                continue; // already reassigned on a previous run.
            }

            $ledger->deletePosting($company, 'payment_voucher', $voucher->id);

            if ($voucher->bill_payment_id) {
                $voucher->billPayment?->delete();
            }

            $payment = $materialBill->billPayments()->create([
                'amount' => $voucher->amount,
                'paid_at' => $voucher->date,
                'method' => $voucher->method,
                'reference' => $voucher->reference,
            ]);

            $voucher->update([
                'bill_id' => $materialBill->id,
                'bill_payment_id' => $payment->id,
                'supplier_id' => $supplier->id,
                'payee_name' => $supplier->name,
                'party_name_ar' => $supplier->name_ar,
            ]);
            $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));

            $this->command?->info("Voucher {$voucher->voucher_number} reassigned to {$supplier->name} (SAR ".number_format((float) $voucher->amount, 2).').');
        }

        $saedBill->amount_paid = $saedBill->billPayments()->sum('amount');
        $saedBill->save();

        $materialBill->amount_paid = $materialBill->billPayments()->sum('amount');
        $materialBill->save();
    }

    private function issuePurchaseReturn(Company $company, Bill $saedBill): void
    {
        $purchaseReturn = PurchaseReturn::create([
            'company_id' => $company->id,
            'bill_id' => $saedBill->id,
            'supplier_id' => $saedBill->supplier_id,
            'branch_id' => $saedBill->branch_id,
            'return_number' => $company->nextPurchaseReturnNumber(),
            'issue_date' => '2026-10-23',
            'reason' => 'Base course and asphalt material purchased directly from Al Jazeera and Dar Al Anajim, supplied to the subcontractor — net cost credited against the contract balance.',
            'reason_type' => 'other',
            'status' => 'issued',
            'currency' => $company->currency ?? 'SAR',
            'exchange_rate' => 1,
        ]);

        foreach (self::GROUPS as $group) {
            $item = new PurchaseReturnItem([
                'purchase_return_id' => $purchaseReturn->id,
                'description' => $group['description'],
                'quantity' => 1,
                'unit_price' => $group['subtotal'],
                'vat_rate' => 0, // The SAED EST bill itself carries no VAT (not VAT-registered) — this credit mirrors that.
            ]);
            $item->recalculate();
            $item->save();
        }

        $purchaseReturn->recalculateTotals();
        app(LedgerPostingService::class)->postPurchaseReturn($purchaseReturn->fresh('items'));

        $this->command?->info("Purchase return {$purchaseReturn->return_number} issued against SAED EST bill {$saedBill->bill_number} — SAR ".number_format((float) $purchaseReturn->total, 2).' credited.');
    }
}

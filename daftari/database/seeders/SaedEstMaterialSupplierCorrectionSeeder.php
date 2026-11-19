<?php

namespace Database\Seeders;

use App\Models\BankAccount;
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
 * decreasing the vat" — Al Jazeera and Dar Al Manajim are NOT part of the
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
 * or Dar Al Manajim, so ~SAR 20,159.35 of real, recoverable input VAT
 * was never captured for the VAT return; (2) the full SAR 373,650.00
 * SAED EST accrual still expensed this same material cost a second
 * time, on top of whatever these new bills would expense.
 *
 * This seeder completes the correction:
 *   1. Creates the two real suppliers and a real, 15%-VAT Bill for each,
 *      settled by the SAME real vouchers moved off the SAED EST bill —
 *      no new cash for those, just the exact same real payments, now
 *      correctly attributed to who was actually paid.
 *   2. Issues a Purchase Return against the SAED EST bill for the
 *      combined net cost (SAR 134,395.68), crediting Shahid's balance
 *      and reversing that same amount of the original Expense accrual —
 *      so the material is expensed exactly once, not twice.
 *
 * Net financial-statement effect: zero. Every riyal of real cash still
 * moves exactly as it did before (see the bank/cash invariant test).
 * Total Operating Expense recognized across all three suppliers combined
 * is still exactly SAR 373,650.00. The only real change is where that
 * expense and its VAT land: SAR 134,395.68 of it (plus SAR 20,159.35 of
 * input VAT that was previously missed entirely) now sits correctly on
 * Al Jazeera's and Dar Al Manajim's own bills instead of buried inside
 * SAED EST's.
 *
 * Dar Al Manajim's figures are now the REAL ones, straight off their own
 * tax invoice (CRED-2568, 25-Oct-2026): Taxable Amount SAR 107,352.20,
 * VAT SAR 16,102.83, Total SAR 123,455.03 — confirming the SAR 107,352.20
 * net figure used since the very first pass was right all along (an
 * earlier revision of this seeder second-guessed it down to SAR
 * 106,665.74 based on the 3 vouchers' cash total alone; that was wrong,
 * reverted here). "remaining 789 due is also paid" — their own SOA shows
 * SAR 789.43 still open after those 3 vouchers (SAR 122,665.60 paid
 * against a SAR 123,455.03 invoice); this seeder also records that
 * 4th, real settlement as its own new voucher. It does not change what's
 * credited to Shahid — that's fixed at the invoice's own net cost,
 * independent of how/when Dynamic Core finishes paying Dar Al Manajim.
 *
 * Known, pre-existing limitation NOT fixed here (out of scope — it's how
 * every bill with a purchase return behaves in this codebase, not
 * something specific to this one): Bill::balanceDue() never nets out
 * issued Purchase Returns, only amount_paid. So after this runs, the
 * SAED EST bill's own balanceDue() will overstate what's really left to
 * settle by the full SAR 134,395.68 credited — the Subcontract module's
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
        'dar_al_manajim' => [
            'name' => 'DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD',
            'name_ar' => 'شركة دار المناجم للتعدين والتصنيع ذ م م',
            'vat_number' => '300075266900003',
            'cr_number' => '1010228923',
            'city' => 'Jeddah',
            'country' => 'Saudi Arabia',
            'address_line_1' => 'Ali Al Khal St, Al Nakheel, Building 3242',
            'postal_code' => '23241',
            'description' => 'Asphalt Wearing Course Class C-SEC — supplied to Shahid, Jumum Yard Project (invoice CRED-2568)',
            'voucher_numbers' => ['PV-00029', 'PV-00032', 'PV-00033'],
            'bill_date' => '2026-10-20',
            'supplier_reference' => 'CRED-2568',
            'subtotal' => 107352.20,
            'vat_total' => 16102.83,
            'total' => 123455.03,
            // "remaining 789 due is also paid" — their own SOA (24-Oct-2026)
            // shows SAR 789.43 still open after the 3 vouchers above
            // (SAR 122,665.60 against the SAR 123,455.03 invoice total).
            'remaining_payment' => [
                'amount' => 789.43,
                'date' => '2026-10-29',
                'reference' => 'CRED-2568 remaining balance settled',
            ],
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
                    [
                        'type' => 'company',
                        'name_ar' => $group['name_ar'] ?? null,
                        'vat_number' => $group['vat_number'] ?? null,
                        'cr_number' => $group['cr_number'] ?? null,
                        'city' => $group['city'] ?? null,
                        'country' => $group['country'] ?? 'Saudi Arabia',
                        'address_line_1' => $group['address_line_1'] ?? null,
                        'postal_code' => $group['postal_code'] ?? null,
                    ]
                );

                $bill = $this->createAndPostMaterialBill($company, $project, $supplier, $group);
                $this->reassignVouchers($company, $ledger, $saedBill, $bill, $supplier, $group);

                if (isset($group['remaining_payment'])) {
                    $this->payRemainingBalance($company, $project, $ledger, $bill, $supplier, $group);
                }
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
            'supplier_reference' => $group['supplier_reference'] ?? $group['description'],
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

    /**
     * "remaining 789 due is also paid" — a real, new settlement that
     * isn't among the original 28 vouchers at all. Posted through the
     * normal voucher/GL path, same as ShahidTransferOct29Seeder's own
     * one-off real transfer.
     */
    private function payRemainingBalance(Company $company, Project $project, LedgerPostingService $ledger, Bill $materialBill, Supplier $supplier, array $group): void
    {
        $remaining = $group['remaining_payment'];

        $alreadyRecorded = PaymentVoucher::where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->where('amount', $remaining['amount'])
            ->where('reference', $remaining['reference'])
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $operating = BankAccount::where('company_id', $company->id)->where('name', 'SNB — Operating Account')->first();

        if (! $operating) {
            $this->command?->error('SNB — Operating Account bank account not found — skipping the remaining balance payment.');

            return;
        }

        $voucher = PaymentVoucher::create([
            'company_id' => $company->id,
            'bank_account_id' => $operating->id,
            'project_id' => $project->id,
            'bill_id' => $materialBill->id,
            'party_type' => 'supplier',
            'supplier_id' => $supplier->id,
            'payee_name' => $supplier->name,
            'party_name_ar' => $supplier->name_ar,
            'voucher_number' => $this->nextFreeVoucherNumber($company),
            'date' => $remaining['date'],
            'amount' => $remaining['amount'],
            'method' => 'bank_transfer',
            'reference' => $remaining['reference'],
            'notes' => $remaining['reference'],
            'status' => 'issued',
        ]);

        $payment = $materialBill->billPayments()->create([
            'amount' => $remaining['amount'],
            'paid_at' => $voucher->date,
            'method' => $voucher->method,
            'reference' => $voucher->reference,
        ]);

        $voucher->update(['bill_payment_id' => $payment->id]);
        $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));

        $materialBill->amount_paid = $materialBill->billPayments()->sum('amount');
        $materialBill->save();

        $this->command?->info("Voucher {$voucher->voucher_number} created and posted — SAR ".number_format((float) $remaining['amount'], 2)." remaining balance to {$supplier->name}.");
    }

    /**
     * The original 28-voucher backfill (SaedEstSubcontractorPaymentsSeeder)
     * creates vouchers with their real, already-used numbers directly,
     * without ever advancing company->next_payment_voucher_number — on
     * the real production database that's harmless, since every one of
     * those real numbers was already issued through the normal UI (which
     * does advance the counter), so it's already well ahead. In a fresh
     * seed/test database, though, the counter starts at 1 and would
     * collide with one of those hardcoded real numbers. Skipping past
     * anything already taken keeps this safe in both cases.
     */
    private function nextFreeVoucherNumber(Company $company): string
    {
        do {
            $candidate = $company->nextPaymentVoucherNumber();
        } while (PaymentVoucher::where('company_id', $company->id)->where('voucher_number', $candidate)->exists());

        return $candidate;
    }

    private function issuePurchaseReturn(Company $company, Bill $saedBill): void
    {
        $purchaseReturn = PurchaseReturn::create([
            'company_id' => $company->id,
            'bill_id' => $saedBill->id,
            'supplier_id' => $saedBill->supplier_id,
            'branch_id' => $saedBill->branch_id,
            'return_number' => $company->nextPurchaseReturnNumber(),
            'issue_date' => '2026-10-29',
            'reason' => 'Base course and asphalt material purchased directly from Al Jazeera and Dar Al Manajim, supplied to the subcontractor — net cost credited against the contract balance.',
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

<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\BillItem;
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
 * "supplier bill create bill against subcontractor and assign payment
 * voucher against those bill to generate account statemt of
 * supplier/subcontractor as well" — every one of the 29 real SAED EST
 * payment vouchers was posted as a direct, unaccrued operating expense
 * (no bill_id), so the Supplier Account Statement (which only ever
 * reads Bill + BillPayment — see ReportController::supplierStatementLines())
 * has always shown nothing at all for this subcontractor. This creates
 * the real accrual Bill behind the contract and reassigns every real
 * voucher to settle it, through the exact same path
 * PaymentVoucherController::update() uses when a user switches a
 * voucher from one bill to another by hand: delete its old journal
 * entry, create a BillPayment, re-post. The Subcontractor Payments
 * module (milestones, net/gross, report categories) is untouched —
 * it's a separate tracking layer over the same PaymentVoucher rows and
 * doesn't read bill_id at all.
 *
 * THIS CHANGES REAL FINANCIAL STATEMENTS, not just a report's shape:
 *   - Bank/cash balances are exactly unchanged — every voucher still
 *     moves the same real money, on the same date, the same way. The
 *     journal entry's own Accounts Payable debit always equals the
 *     voucher's full real (gross) amount, same as the bank credit —
 *     that side can never be anything but gross, or the entry wouldn't
 *     balance. So the GL's own Accounts Payable balance for this
 *     supplier settles to SAR 11,584.40 (SAR 373,650.00 billed minus
 *     SAR 362,065.60 real cash paid across all 29 vouchers) regardless
 *     of what BillPayment.amount below is set to.
 *   - The Income Statement changes. Before this: ~SAR 362,065.60 of
 *     this subcontractor's cost was expensed piecemeal, voucher by
 *     voucher, as cash left the bank. After: the bill posts the FULL
 *     contract value (SAR 373,650.00) as Operating Expense in one
 *     entry, dated to the contract's start (2026-09-25) — proper
 *     accrual accounting, appropriate since the work is complete.
 *
 * BillPayment.amount is NOT simply the voucher's real cash figure,
 * though: "it added asphalt dar al anajim payment voucher listed with
 * vat i need to list them without vat and same for aljazera" — the Dar
 * Al Anajim and Al Jazeera vouchers settle this bill at the same net
 * (ex-VAT) figure already worked out in the Subcontractor Payments
 * module, not their real gross cash amount, so the statement lists
 * what's actually applied against the SAR 373,650.00 (itself a net
 * figure). Every other voucher has no such VAT difference, so its
 * BillPayment is its own real amount either way.
 *
 * Known, accepted consequence: Bill::balanceDue() (SAR 373,650.00 minus
 * the sum of these now-partly-net BillPayments, ≈ SAR 30,954.33) will
 * read HIGHER than the GL's own true Accounts Payable balance above
 * (SAR 11,584.40) — the SAR 19,369.93 gap is exactly the gross-vs-net
 * difference on those two groups, cash that really left the bank and
 * really relieved Accounts Payable in the GL, but was never counted
 * toward the SAR 373,650.00 net contract value. Anything that reads
 * Bill::balanceDue() (the Bill show page, Aged Payables) will reflect
 * that same gap — only the GL's own ledger/trial balance stays the
 * literal truth of what moved.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstSubcontractorBillSeeder"
 *
 * Idempotent: does nothing if a bill already exists for this supplier
 * on this project, and only reassigns vouchers that aren't already
 * linked to a bill — safe to re-run, including after new vouchers have
 * been added since the last run (it picks those up too).
 */
class SaedEstSubcontractorBillSeeder extends Seeder
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

        if (! $project || ! $supplier || ! $subcontract) {
            $this->command?->error('The Jumum Yard project, SAED EST supplier, or its Subcontract was not found — run SaedEstSubcontractorPaymentsSeeder first.');

            return;
        }

        $bill = Bill::where('company_id', $company->id)->where('supplier_id', $supplier->id)->where('project_id', $project->id)->first();

        DB::transaction(function () use ($company, $project, $supplier, $bill) {
            $bill = $bill ?: $this->createAndPostBill($company, $project, $supplier);
            $this->assignVouchersToBill($company, $project, $supplier, $bill);
        });
    }

    private function createAndPostBill(Company $company, Project $project, Supplier $supplier): Bill
    {
        $bill = Bill::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'branch_id' => $company->default_branch_id,
            'bill_number' => $company->nextBillNumber(),
            'supplier_reference' => 'Subbase, Base Course and Asphalt work',
            'status' => 'draft',
            'bill_date' => '2026-09-25',
            'currency' => $company->currency ?? 'SAR',
            'exchange_rate' => 1,
            'notes' => 'Jumum Yard Project subcontract — matches the Subcontractor Payments module\'s own BOQ.',
        ]);

        // Mirrors the Subcontract's own 4-line BOQ exactly (see
        // SaedEstSubcontractorPaymentsSeeder::seedBoqItems()) — vat_rate
        // 0 on every line since SAED EST carries no VAT number (not
        // VAT-registered), not the company's own default rate.
        foreach ([
            ['description' => 'Sub base', 'quantity' => 10000, 'unit_price' => 6.00],
            ['description' => 'Base Course', 'quantity' => 10000, 'unit_price' => 14.00],
            ['description' => 'Asphalt', 'quantity' => 4100, 'unit_price' => 36.50],
            ['description' => 'Sub base+ABC', 'quantity' => 1200, 'unit_price' => 20.00],
        ] as $i => $row) {
            $line = new BillItem([
                'bill_id' => $bill->id, 'description' => $row['description'],
                'quantity' => $row['quantity'], 'unit_price' => $row['unit_price'],
                'vat_rate' => 0, 'sort_order' => $i,
            ]);
            $line->recalculate();
            $line->save();
        }

        $bill->recalculateTotals();
        $bill->update(['status' => 'posted']);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh('items.item'));

        $this->command?->info("Bill {$bill->bill_number} created and posted — SAR ".number_format((float) $bill->total, 2).' accrued for '.$supplier->name.'.');

        return $bill;
    }

    private function assignVouchersToBill(Company $company, Project $project, Supplier $supplier, Bill $bill): void
    {
        $ledger = app(LedgerPostingService::class);

        $vouchers = PaymentVoucher::where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->where('project_id', $project->id)
            ->where('status', 'issued')
            ->whereNull('bill_id')
            ->orderBy('date')
            ->get();

        foreach ($vouchers as $voucher) {
            $ledger->deletePosting($company, 'payment_voucher', $voucher->id);

            // "it added asphalt dar al anajim payment voucher listed
            // with vat i need to list them without vat and same for
            // aljazera" — this bill settles against the net (ex-VAT)
            // value already worked out in the Subcontractor Payments
            // module (see SaedEstSubcontractorPaymentsSeeder's own
            // Al Jazeera ÷1.15 and Dar Al Anajim proportional-to-
            // 107,352.20 corrections), not the voucher's real gross
            // cash figure — falls back to the voucher's own amount for
            // every other voucher, where no such VAT difference exists.
            $netAmount = round((float) SubcontractPayment::where('payment_voucher_id', $voucher->id)->sum('amount'), 2);
            $netAmount = $netAmount > 0.004 ? $netAmount : (float) $voucher->amount;

            $payment = $bill->billPayments()->create([
                'amount' => $netAmount,
                'paid_at' => $voucher->date,
                'method' => $voucher->method,
                'reference' => $voucher->reference,
            ]);

            $voucher->update(['bill_id' => $bill->id, 'bill_payment_id' => $payment->id]);
            $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));

            $this->command?->info("Voucher {$voucher->voucher_number} (SAR ".number_format($netAmount, 2).' applied, SAR '.number_format((float) $voucher->amount, 2).' cash) reassigned to settle bill '.$bill->bill_number.'.');
        }

        $bill->amount_paid = $bill->billPayments()->sum('amount');
        $bill->save();

        $this->command?->info('Bill '.$bill->bill_number.' balance due: SAR '.number_format($bill->balanceDue(), 2).'.');
    }
}

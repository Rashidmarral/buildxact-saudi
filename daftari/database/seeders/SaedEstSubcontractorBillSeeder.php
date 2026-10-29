<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
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
 *     moves the same real money, on the same date, the same way.
 *   - The Income Statement changes. Before this: ~SAR 362,065.60 of
 *     this subcontractor's cost was expensed piecemeal, voucher by
 *     voucher, as cash left the bank. After: the bill posts the FULL
 *     contract value (SAR 373,650.00) as Operating Expense in one
 *     entry, dated to the contract's start (2026-09-25) — proper
 *     accrual accounting (the cost is recognized when the work was
 *     done/committed, not only as cash was paid), appropriate here
 *     since the subcontracted work is complete. That moves the
 *     unpaid SAR 11,584.40 balance of this cost onto the books now
 *     instead of only when it's eventually paid.
 *   - Accounts Payable carries that same SAR 11,584.40 balance until
 *     it's settled by a future voucher/bill payment.
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

            $payment = $bill->billPayments()->create([
                'amount' => $voucher->amount,
                'paid_at' => $voucher->date,
                'method' => $voucher->method,
                'reference' => $voucher->reference,
            ]);

            $voucher->update(['bill_id' => $bill->id, 'bill_payment_id' => $payment->id]);
            $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));

            $this->command?->info("Voucher {$voucher->voucher_number} (SAR ".number_format((float) $voucher->amount, 2).') reassigned to settle bill '.$bill->bill_number.'.');
        }

        $bill->amount_paid = $bill->billPayments()->sum('amount');
        $bill->save();

        $this->command?->info('Bill '.$bill->bill_number.' balance due: SAR '.number_format($bill->balanceDue(), 2).'.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ReceiptVoucher;
use App\Models\ReceiptVoucherAllocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 12: you confirmed RV-00010 (SAR 157,510.61,
 * reference FT26293FQMQP, from Sada Al Jeul Construction
 * Establishment) should be split across the two invoices it actually
 * funded — INV-00003 (SAR 72,489.39) and INV-2026-00005 (SAR
 * 85,021.22) — matching the InvoicePayment rows that already exist for
 * that exact reference and date.
 *
 * This is traceability only — no GL change. The voucher's own journal
 * entry already correctly credits Accounts Receivable for the full
 * SAR 157,510.61 (confirmed against the real ledger), and both
 * InvoicePayment rows already exist and already reduced each
 * invoice's amount_paid — only the ReceiptVoucherAllocation link
 * between the voucher and those two existing payments was missing (see
 * that table's own migration for why one receipt can settle more than
 * one invoice). This never creates a new InvoicePayment, never touches
 * invoice.amount_paid, and never re-posts the voucher.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\ReceiptVoucherRv00010AllocationSeeder"
 *
 * Idempotent: skips if the voucher already has any allocation rows.
 */
class ReceiptVoucherRv00010AllocationSeeder extends Seeder
{
    private const VOUCHER_NUMBER = 'RV-00010';

    private const REFERENCE = 'FT26293FQMQP';

    private const SPLITS = [
        ['invoice_number' => 'INV-00003', 'amount' => 72489.39],
        ['invoice_number' => 'INV-2026-00005', 'amount' => 85021.22],
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $voucher = ReceiptVoucher::where('company_id', $company->id)
            ->where('voucher_number', self::VOUCHER_NUMBER)
            ->first();

        if (! $voucher) {
            $this->command?->error(self::VOUCHER_NUMBER.' was not found — nothing changed.');

            return;
        }

        if ($voucher->allocations()->exists()) {
            $this->command?->info(self::VOUCHER_NUMBER.' already has allocations — skipping.');

            return;
        }

        if ($voucher->invoice_id || $voucher->invoice_payment_id) {
            $this->command?->error(self::VOUCHER_NUMBER.' already points at a single invoice — aborting, this would conflict with a split.');

            return;
        }

        $rows = [];
        foreach (self::SPLITS as $split) {
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_number', $split['invoice_number'])->first();

            if (! $invoice) {
                $this->command?->error("Invoice {$split['invoice_number']} was not found — aborting without writing anything.");

                return;
            }

            $payment = InvoicePayment::where('invoice_id', $invoice->id)
                ->where('reference', self::REFERENCE)
                ->where('amount', $split['amount'])
                ->first();

            if (! $payment) {
                $this->command?->error("The real InvoicePayment for {$split['invoice_number']} (SAR {$split['amount']}, reference ".self::REFERENCE.') was not found — aborting without writing anything.');

                return;
            }

            $rows[] = ['invoice' => $invoice, 'payment' => $payment, 'amount' => $split['amount']];
        }

        DB::transaction(function () use ($company, $voucher, $rows) {
            foreach ($rows as $row) {
                ReceiptVoucherAllocation::create([
                    'company_id' => $company->id,
                    'receipt_voucher_id' => $voucher->id,
                    'invoice_id' => $row['invoice']->id,
                    'invoice_payment_id' => $row['payment']->id,
                    'amount' => $row['amount'],
                ]);
            }
        });

        $this->command?->info(self::VOUCHER_NUMBER.' linked to '.count($rows).' invoice(s) — no GL change.');
    }
}

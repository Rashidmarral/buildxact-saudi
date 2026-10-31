<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Company;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "we have not issue the final bill this was in testing phase when i
 * am doing testing for our system we are adding the fake invoices at
 * all in our system" — BILL-00003 (East West Establishment, "Supply
 * of sub base course material with transportation", SAR 50,400.00 +
 * SAR 7,560.00 VAT = SAR 57,960.00, posted 2026-09-22) was test data
 * entered while testing the app, not a real purchase. It's still sitting
 * in the live books as 'posted' and unpaid, inflating real Operating
 * Expenses by SAR 50,400.00 and real recoverable input VAT by SAR
 * 7,560.00 until it's voided.
 *
 * Unlike the two earlier test bills (BILL-00001/BILL-00002, both
 * already 'void' with notes literally saying "test bill"/"test
 * purchase order"), this one carries no such marker and is still
 * 'posted' — worth remembering when entering test data going forward:
 * mark it clearly and void it immediately, the same way those two were,
 * so it can never be mistaken for a real transaction.
 *
 * Voids it through the exact same path BillController::void() uses —
 * flips status to 'void' and reverses its journal entry — rather than
 * deleting it, so the real audit trail (a document was entered, then
 * voided, and why) stays intact.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\Bill00003TestDataCleanupSeeder"
 *
 * Idempotent: does nothing once the bill is already void.
 */
class Bill00003TestDataCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $bill = Bill::where('company_id', $company->id)->where('bill_number', 'BILL-00003')->first();

        if (! $bill) {
            $this->command?->error('BILL-00003 was not found — nothing to clean up.');

            return;
        }

        if ($bill->status === 'void') {
            $this->command?->info('BILL-00003 is already void — skipping.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($bill, $ledger) {
            $bill->status = 'void';
            $bill->save();

            $ledger->reverse($bill->company, 'bill', $bill->id, 'Bill BILL-00003 voided — test data entered while testing the app, not a real transaction');
        });

        AuditLog::record('bill.void', $bill, 'Voided bill BILL-00003 — confirmed test data, not a real purchase');

        $this->command?->info('BILL-00003 voided — SAR 50,400.00 of real Operating Expenses and SAR 7,560.00 of input VAT reversed out of the books.');
    }
}

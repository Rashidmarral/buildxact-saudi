<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Account;
use App\Models\AdvanceCreditApplication;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\PrepaymentInvoice;
use App\Models\Salesperson;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Accounting\VatApportionmentService;
use App\Services\MpdfRenderer;
use App\Services\Reports\FinancialReportService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    use ExportsCsv, ResolvesReportPeriod;

    /**
     * A proper three-way tax report: Output Tax (Sales/Invoices), Input Tax
     * (Purchases/Bills), and Expense Tax (Expenses) — the earlier version
     * only ever looked at Invoices and Expenses, silently omitting Bills
     * (Purchases) from input VAT entirely, and only supported a single
     * month/year rather than a real date range or per-tab transaction
     * listing. Net tax position = output tax minus both input categories,
     * matching how a real VAT return nets output against all recoverable
     * input VAT (purchases and expenses alike).
     */
    public function vat(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $tab = in_array($request->query('tab'), ['sales', 'purchases', 'expenses'], true) ? $request->query('tab') : 'sales';

        $salesRows = Invoice::with('client', 'warehouse', 'salesperson')
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
            ->when($request->filled('salesperson_id'), fn ($q) => $q->where('salesperson_id', $request->query('salesperson_id')))
            ->orderByDesc('issue_date')
            ->get();

        $purchaseRows = Bill::with('supplier')
            ->whereBetween('bill_date', [$period['from'], $period['to']])
            ->whereNotIn('status', ['draft', 'void'])
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->query('supplier_id')))
            ->orderByDesc('bill_date')
            ->get();

        $expenseRows = Expense::with('category')
            ->whereBetween('expense_date', [$period['from'], $period['to']])
            ->where('status', '!=', 'rejected')
            ->orderByDesc('expense_date')
            ->get();

        // Neither advance mechanism reduces an invoice's own vat_total, so
        // summing $salesRows->vat_total on its own double-counts VAT that
        // was already recognized earlier: once when a regular invoice later
        // credited via an Advance Credit application was first issued, and
        // once (never, today) for a genuine 386 Prepayment Invoice, whose
        // own VAT otherwise never enters this report at all. This nets both
        // out — see AdvanceCreditApplication/PrepaymentInvoice::appliedToInvoice.
        $prepaymentRows = PrepaymentInvoice::with('client')
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->where('status', 'issued')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
            ->orderByDesc('issue_date')
            ->get();

        $advanceCreditVatAlreadyReported = (float) AdvanceCreditApplication::whereIn('invoice_id', $salesRows->pluck('id'))->sum('vat_amount');
        $prepaymentVatAppliedThisPeriod = (float) PrepaymentInvoice::whereHas('appliedToInvoice', fn ($q) => $q->whereIn('id', $salesRows->pluck('id')))->sum('vat_amount');
        $prepaymentVatDueThisPeriod = (float) $prepaymentRows->sum('vat_amount');

        $outputTaxFromInvoices = (float) $salesRows->sum('vat_total');
        $outputTax = round($outputTaxFromInvoices + $prepaymentVatDueThisPeriod - $advanceCreditVatAlreadyReported - $prepaymentVatAppliedThisPeriod, 2);
        $inputTaxPurchases = (float) $purchaseRows->sum('vat_total');
        $expenseTax = (float) $expenseRows->sum('vat_amount');

        $summary = [
            'outputTax' => $outputTax,
            'outputTaxFromInvoices' => $outputTaxFromInvoices,
            'prepaymentRows' => $prepaymentRows,
            'prepaymentVatDueThisPeriod' => $prepaymentVatDueThisPeriod,
            'advanceCreditVatAlreadyReported' => $advanceCreditVatAlreadyReported,
            'prepaymentVatAppliedThisPeriod' => $prepaymentVatAppliedThisPeriod,
            'netSalesExclTax' => (float) $salesRows->sum('subtotal') + (float) $prepaymentRows->sum('amount'),
            'inputTaxPurchases' => $inputTaxPurchases,
            'netPurchasesExclTax' => (float) $purchaseRows->sum('subtotal'),
            'expenseTax' => $expenseTax,
            'totalExpenses' => (float) $expenseRows->sum(fn ($e) => $e->gross_amount ?? $e->amount),
            'netTaxPosition' => $outputTax - $inputTaxPurchases - $expenseTax,
        ];

        $summary += app(VatApportionmentService::class)->calculate($company, $salesRows, $inputTaxPurchases + $expenseTax);
        $summary['netTaxPosition'] = $outputTax - $summary['netRecoverableInputTax'];

        if ($request->query('export') === 'csv') {
            return $this->vatCsvExport($tab, $salesRows, $purchaseRows, $expenseRows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.vat', [
                'company' => $company, 'period' => $period, 'tab' => $tab,
                'salesRows' => $salesRows, 'purchaseRows' => $purchaseRows, 'expenseRows' => $expenseRows,
            ] + $summary);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="tax-report-'.$tab.'.pdf"']);
        }

        return view('user.reports.vat', [
            'company' => $company,
            'period' => $period,
            'tab' => $tab,
            'salesRows' => $salesRows,
            'purchaseRows' => $purchaseRows,
            'expenseRows' => $expenseRows,
            'clients' => Client::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
        ] + $summary);
    }

    private function vatCsvExport(string $tab, $salesRows, $purchaseRows, $expenseRows)
    {
        return match ($tab) {
            'purchases' => $this->csvResponse('tax-report-purchases.csv',
                [__('Date'), __('Reference'), __('Supplier'), __('Tax Number'), __('Net Amount Excl. Tax'), __('Discount'), __('Tax Amount'), __('Total Amount'), __('Payment Status')],
                $purchaseRows->map(fn (Bill $b) => [
                    $b->bill_date?->format('Y-m-d'), $b->bill_number, $b->supplier->name ?? '', $b->supplier->vat_number ?? '',
                    number_format((float) $b->subtotal, 2, '.', ''), number_format((float) $b->discount_total, 2, '.', ''),
                    number_format((float) $b->vat_total, 2, '.', ''), number_format((float) $b->total, 2, '.', ''),
                    $this->paymentStatusLabel((float) $b->amount_paid, (float) $b->total),
                ])),
            'expenses' => $this->csvResponse('tax-report-expenses.csv',
                [__('Date'), __('Reference'), __('Vendor'), __('Category'), __('Net Amount Excl. Tax'), __('Tax Amount'), __('Total Amount'), __('Status')],
                $expenseRows->map(fn (Expense $e) => [
                    $e->expense_date?->format('Y-m-d'), $e->reference, $e->vendor_name, $e->category->name ?? '',
                    number_format((float) $e->amount, 2, '.', ''), number_format((float) $e->vat_amount, 2, '.', ''),
                    number_format((float) ($e->gross_amount ?? $e->amount), 2, '.', ''), ucfirst((string) $e->status),
                ])),
            default => $this->csvResponse('tax-report-sales.csv',
                [__('Date'), __('Reference'), __('Customer'), __('Tax Number'), __('Salesperson'), __('Net Amount Excl. Tax'), __('Discount'), __('Tax Amount'), __('Total Amount'), __('Payment Status')],
                $salesRows->map(fn (Invoice $i) => [
                    $i->issue_date?->format('Y-m-d'), $i->invoice_number, $i->client->name ?? '', $i->client->vat_number ?? '', $i->salesperson->name ?? '',
                    number_format((float) $i->subtotal, 2, '.', ''), number_format((float) $i->discount_total, 2, '.', ''),
                    number_format((float) $i->vat_total, 2, '.', ''), number_format((float) $i->total, 2, '.', ''),
                    $this->paymentStatusLabel((float) $i->amount_paid, (float) $i->total),
                ])),
        };
    }

    private function paymentStatusLabel(float $amountPaid, float $total): string
    {
        if ($total > 0 && $amountPaid >= $total) {
            return __('Paid');
        }

        return $amountPaid > 0 ? __('Partial') : __('Unpaid');
    }

    public function trialBalance(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $rows = app(FinancialReportService::class)->trialBalance($company, $period['from'], $period['to']);

        $totalDebit = $rows->sum('debit');
        $totalCredit = $rows->sum('credit');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('trial-balance.csv', [__('Code'), __('Account'), __('Debit'), __('Credit')],
                $rows->map(fn ($r) => [$r['account']->code, $r['account']->name, number_format($r['debit'], 2, '.', ''), number_format($r['credit'], 2, '.', '')]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.trial-balance', [
                'company' => $company, 'period' => $period, 'rows' => $rows, 'totalDebit' => $totalDebit, 'totalCredit' => $totalCredit,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="trial-balance.pdf"']);
        }

        return view('user.reports.trial-balance', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $company = Auth::user()->company;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();

        $data = app(FinancialReportService::class)->balanceSheet($company, $asOf);

        if ($request->query('export') === 'csv') {
            $rows = collect()
                ->concat($data['assets']->map(fn ($r) => [__('Assets'), $r['account']->code, $r['account']->name, number_format($r['balance'], 2, '.', '')]))
                ->concat($data['liabilities']->map(fn ($r) => [__('Liabilities'), $r['account']->code, $r['account']->name, number_format($r['balance'], 2, '.', '')]))
                ->concat($data['equity']->map(fn ($r) => [__('Equity'), $r['account']->code ?? 'CURRENT_EARNINGS', $r['account']?->name ?? $r['label'], number_format($r['balance'], 2, '.', '')]));

            return $this->csvResponse('balance-sheet.csv', [__('Section'), __('Code'), __('Account'), __('Balance')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.balance-sheet', ['company' => $company, 'asOf' => $asOf] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="balance-sheet.pdf"']);
        }

        return view('user.reports.balance-sheet', ['company' => $company, 'asOf' => $asOf] + $data);
    }

    public function incomeStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $statement = app(FinancialReportService::class)->incomeStatement($company, $period['from'], $period['to']);
        $revenueLines = $statement['revenueLines'];
        $expenseLines = $statement['expenseLines'];
        $netSales = $statement['netSales'];
        $grossProfit = $statement['grossProfit'];
        $operatingProfit = $statement['operatingProfit'];
        $netProfit = $statement['netProfit'];

        if ($request->query('export') === 'csv') {
            $rows = collect()
                ->concat($revenueLines->map(fn ($r) => [__('Revenue'), $r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', '')]))
                ->concat($expenseLines->map(fn ($r) => [__('Expenses'), $r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', '')]));

            return $this->csvResponse('income-statement.csv', [__('Section'), __('Code'), __('Account'), __('Amount')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.income-statement', [
                'company' => $company, 'period' => $period, 'revenueLines' => $revenueLines, 'expenseLines' => $expenseLines,
                'netSales' => $netSales, 'grossProfit' => $grossProfit, 'operatingProfit' => $operatingProfit, 'netProfit' => $netProfit,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="income-statement.pdf"']);
        }

        return view('user.reports.income-statement', [
            'company' => $company,
            'period' => $period,
            'revenueLines' => $revenueLines,
            'expenseLines' => $expenseLines,
            'netSales' => $netSales,
            'grossProfit' => $grossProfit,
            'operatingProfit' => $operatingProfit,
            'netProfit' => $netProfit,
        ]);
    }

    public function sales(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $items = collect();
        Invoice::with('items', 'client', 'salesperson')
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
            ->when($request->filled('salesperson_id'), fn ($q) => $q->where('salesperson_id', $request->query('salesperson_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('invoice_number', 'like', '%'.$request->query('search').'%'))
            ->orderByDesc('issue_date')
            ->get()
            ->each(function (Invoice $invoice) use (&$items, $request) {
                foreach ($invoice->items as $line) {
                    if ($request->filled('item_id') && $line->item_id != $request->query('item_id')) {
                        continue;
                    }
                    if ($request->filled('min_amount') && $line->line_total < (float) $request->query('min_amount')) {
                        continue;
                    }
                    if ($request->filled('max_amount') && $line->line_total > (float) $request->query('max_amount')) {
                        continue;
                    }
                    $items->push((object) [
                        'invoice' => $invoice,
                        'line' => $line,
                    ]);
                }
            });

        $sort = $request->query('sort', 'date');
        $direction = $request->query('direction', 'desc');
        $items = $items->sortBy(fn ($row) => match ($sort) {
            'amount' => $row->line->line_total,
            'customer' => $row->invoice->client->name ?? '',
            default => $row->invoice->issue_date,
        }, SORT_REGULAR, $direction === 'desc')->values();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('sales-report.csv', [__('Date'), __('Invoice'), __('Customer'), __('Description'), __('Total')],
                $items->map(fn ($r) => [$r->invoice->issue_date->format('Y-m-d'), $r->invoice->invoice_number, $r->invoice->client->name ?? '', $r->line->description, number_format($r->line->line_total, 2, '.', '')]));
        }

        $salesSummary = [
            'invoiceCount' => $items->pluck('invoice.id')->unique()->count(),
            'lineCount' => $items->count(),
            'discountTotal' => $items->pluck('invoice')->unique('id')->sum('discount_total'),
            'taxableSales' => $items->sum(fn ($r) => $r->line->quantity * $r->line->unit_price),
            'taxTotal' => $items->sum('line.vat_amount'),
            'totalSales' => $items->sum('line.line_total'),
        ];

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.sales', [
                'company' => $company, 'period' => $period, 'items' => $items,
            ] + $salesSummary);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="sales-report.pdf"']);
        }

        return view('user.reports.sales', [
            'company' => $company,
            'period' => $period,
            'items' => $items,
            'clients' => Client::orderBy('name')->get(),
            'salespersons' => Salesperson::orderBy('name')->get(),
            'products' => Item::orderBy('name')->get(),
        ] + $salesSummary);
    }

    public function expenses(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $expenses = Expense::with('category')
            ->whereBetween('expense_date', [$period['from'], $period['to']])
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->query('category_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('vendor_name', 'like', '%'.$request->query('search').'%')
                ->orWhere('description', 'like', '%'.$request->query('search').'%')))
            ->orderByDesc('expense_date')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('expense-report.csv', [__('Date'), __('Vendor'), __('Category'), __('Description'), __('Amount'), __('VAT')],
                $expenses->map(fn ($e) => [$e->expense_date->format('Y-m-d'), $e->vendor_name, $e->category->name ?? '', $e->description, number_format($e->amount, 2, '.', ''), number_format($e->vat_amount, 2, '.', '')]));
        }

        $total = $expenses->sum('amount');
        $vatTotal = $expenses->sum('vat_amount');

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.expenses', [
                'company' => $company, 'period' => $period, 'expenses' => $expenses, 'total' => $total, 'vatTotal' => $vatTotal,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="expense-report.pdf"']);
        }

        return view('user.reports.expenses', [
            'company' => $company,
            'period' => $period,
            'expenses' => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'total' => $total,
            'vatTotal' => $vatTotal,
        ]);
    }

    /**
     * The withholding tax actually deducted from non-resident supplier
     * payments during the period — the figures a monthly WHT return to
     * ZATCA is built from. Keyed off PaymentVoucher.wht_amount (the exact
     * payment event that applied the withholding) rather than Bill, since
     * a bill's WHT is applied once on its first payment, not on its date.
     */
    public function whtReturn(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $vouchers = PaymentVoucher::with('bill.supplier', 'bill.whtRate')
            ->where('wht_amount', '>', 0)
            ->where('status', '!=', 'void')
            ->whereBetween('date', [$period['from'], $period['to']])
            ->orderBy('date')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('withholding-tax-report.csv', [__('Date'), __('Supplier'), __('Bill'), __('Category'), __('Rate'), __('Taxable base'), __('WHT amount')],
                $vouchers->map(fn ($v) => [
                    $v->date->format('Y-m-d'),
                    $v->bill?->supplier?->name,
                    $v->bill?->bill_number,
                    $v->bill?->whtRate?->name,
                    number_format((float) ($v->bill?->whtRate?->rate ?? 0), 2, '.', ''),
                    number_format((float) ($v->bill?->subtotal ?? 0) - (float) ($v->bill?->discount_total ?? 0), 2, '.', ''),
                    number_format((float) $v->wht_amount, 2, '.', ''),
                ]));
        }

        $whtTotal = $vouchers->sum('wht_amount');

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.wht-return', [
                'company' => $company, 'period' => $period, 'vouchers' => $vouchers, 'total' => $whtTotal,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="withholding-tax-report.pdf"']);
        }

        return view('user.reports.wht-return', [
            'company' => $company,
            'period' => $period,
            'vouchers' => $vouchers,
            'total' => $whtTotal,
        ]);
    }

    public function cashFlow(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $data = app(FinancialReportService::class)->cashFlow($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            $rows = collect()
                ->push([__('Operating activities'), '', ''])
                ->push(['', __('Net income'), number_format($data['netIncome'], 2, '.', '')])
                ->push(['', __('Depreciation'), number_format($data['depreciation'], 2, '.', '')])
                ->concat($data['workingCapitalLines']->map(fn ($r) => ['', $r['account']->name, number_format($r['cashEffect'], 2, '.', '')]))
                ->push(['', __('Net cash from operating activities'), number_format($data['operatingTotal'], 2, '.', '')])
                ->push([__('Investing activities'), '', ''])
                ->push(['', __('Fixed asset acquisitions'), number_format(-$data['acquisitions'], 2, '.', '')])
                ->push(['', __('Fixed asset disposal proceeds'), number_format($data['disposalProceeds'], 2, '.', '')])
                ->push(['', __('Net cash from investing activities'), number_format($data['investingTotal'], 2, '.', '')])
                ->push([__('Financing activities'), '', ''])
                ->concat($data['equityLines']->map(fn ($r) => ['', $r['account']->name, number_format($r['change'], 2, '.', '')]))
                ->push(['', __('Net cash from financing activities'), number_format($data['financingTotal'], 2, '.', '')])
                ->push(['', __('Net change in cash'), number_format($data['netChange'], 2, '.', '')])
                ->push(['', __('Opening cash & bank'), number_format($data['openingCash'], 2, '.', '')])
                ->push(['', __('Closing cash & bank'), number_format($data['closingCash'], 2, '.', '')]);

            return $this->csvResponse('cash-flow.csv', [__('Section'), __('Line'), __('Amount')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.cash-flow', ['company' => $company, 'period' => $period] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="cash-flow.pdf"']);
        }

        return view('user.reports.cash-flow', ['company' => $company, 'period' => $period] + $data);
    }

    public function accountStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $type = $request->query('type', 'customer') === 'supplier' ? 'supplier' : 'customer';

        $party = null;
        $lines = collect();
        $openingBalance = 0.0;

        if ($type === 'customer' && $request->filled('client_id')) {
            $party = Client::find($request->query('client_id'));
            [$lines, $openingBalance] = $this->customerStatementLines($party, $period);
        } elseif ($type === 'supplier' && $request->filled('supplier_id')) {
            $party = Supplier::find($request->query('supplier_id'));
            [$lines, $openingBalance] = $this->supplierStatementLines($party, $period);
        }

        if ($request->query('export') === 'csv' && $party) {
            $rows = collect([['', __('Opening balance'), '', '', number_format($openingBalance, 2, '.', '')]])
                ->concat($lines->map(fn ($l) => [$l->date->format('Y-m-d'), $l->description, $l->debit > 0 ? number_format($l->debit, 2, '.', '') : '', $l->credit > 0 ? number_format($l->credit, 2, '.', '') : '', number_format($l->balance, 2, '.', '')]));

            return $this->csvResponse('account-statement.csv', [__('Date'), __('Description'), __('Debit'), __('Credit'), __('Balance')], $rows);
        }

        // A single bilingual PDF replaces the old separate pdf-ar/pdf-en
        // exports (each monolingual, on the old plain layout) — the
        // shared report-PDF design shows both languages side by side
        // already, so there's no longer a reason to pick one. Both
        // export values are still accepted so any saved/bookmarked link
        // keeps working.
        if (in_array($request->query('export'), ['pdf', 'pdf-ar', 'pdf-en']) && $party) {
            $closingBalance = $lines->last()->balance ?? $openingBalance;

            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.account-statement', [
                'company' => $company, 'period' => $period, 'party' => $party, 'type' => $type,
                'lines' => $lines, 'openingBalance' => $openingBalance, 'closingBalance' => $closingBalance,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="account-statement.pdf"']);
        }

        return view('user.reports.account-statement', [
            'company' => $company,
            'period' => $period,
            'type' => $type,
            'clients' => Client::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'party' => $party,
            'lines' => $lines,
            'openingBalance' => $openingBalance,
        ]);
    }

    /**
     * Every customer ever billed, with this period's activity (invoiced,
     * collected, VAT) alongside their live outstanding balance — the
     * "Customer Balance Summary" every modern accounting system offers
     * next to a single-party statement (accountStatement() above) and
     * the bucketed Aging report: an at-a-glance view across the whole
     * book, with each row drilling into the detailed statement for that
     * one customer.
     */
    public function customers(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $rows = $this->partyRows(
            Invoice::with('client')->whereNotIn('status', ['draft', 'cancelled'])->get(),
            InvoicePayment::class, 'invoice_id', 'client',
            $period,
        );

        $rows = $this->sortAndSearchRows($rows, $request, fn ($row) => $row['party']->display_name ?? $row['party']->name);

        $summary = [
            'totalOutstanding' => $rows->sum('outstanding'),
            'totalCollected' => $rows->sum('received'),
            'totalInvoiced' => $rows->sum('invoiced'),
            'partyCount' => $rows->count(),
            'topParty' => $rows->sortByDesc('invoiced')->first(),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('customer-report.csv',
                [__('Customer'), __('VAT No.'), __('Invoices this period'), __('Invoiced'), __('VAT collected'), __('Received'), __('Outstanding'), __('Last invoice')],
                $rows->map(fn ($r) => [
                    $r['party']->display_name ?? $r['party']->name, $r['party']->vat_number ?? '',
                    $r['count'], number_format($r['invoiced'], 2, '.', ''), number_format($r['vat'], 2, '.', ''),
                    number_format($r['received'], 2, '.', ''), number_format($r['outstanding'], 2, '.', ''),
                    $r['lastDate']?->format('Y-m-d') ?? '',
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.party-report', [
                'company' => $company, 'period' => $period, 'rows' => $rows, 'summary' => $summary,
                'titleEn' => __('CUSTOMER REPORT'), 'titleAr' => 'تقرير العملاء',
                'partyLabelEn' => __('Customer'), 'partyLabelAr' => 'العميل',
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="customer-report.pdf"']);
        }

        return view('user.reports.customers', ['company' => $company, 'period' => $period, 'rows' => $rows] + $summary);
    }

    /**
     * Mirrors customers() for the payables side — every supplier ever
     * billed, this period's purchases/payments, and their live
     * outstanding balance.
     */
    public function suppliers(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $rows = $this->partyRows(
            Bill::with('supplier')->whereNotIn('status', ['draft', 'void'])->get(),
            BillPayment::class, 'bill_id', 'supplier',
            $period,
        );

        $rows = $this->sortAndSearchRows($rows, $request, fn ($row) => $row['party']->display_name ?? $row['party']->name);

        $summary = [
            'totalOutstanding' => $rows->sum('outstanding'),
            'totalCollected' => $rows->sum('received'),
            'totalInvoiced' => $rows->sum('invoiced'),
            'partyCount' => $rows->count(),
            'topParty' => $rows->sortByDesc('invoiced')->first(),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('supplier-report.csv',
                [__('Supplier'), __('VAT No.'), __('Bills this period'), __('Billed'), __('VAT paid'), __('Paid'), __('Outstanding'), __('Last bill')],
                $rows->map(fn ($r) => [
                    $r['party']->display_name ?? $r['party']->name, $r['party']->vat_number ?? '',
                    $r['count'], number_format($r['invoiced'], 2, '.', ''), number_format($r['vat'], 2, '.', ''),
                    number_format($r['received'], 2, '.', ''), number_format($r['outstanding'], 2, '.', ''),
                    $r['lastDate']?->format('Y-m-d') ?? '',
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.party-report', [
                'company' => $company, 'period' => $period, 'rows' => $rows, 'summary' => $summary,
                'titleEn' => __('SUPPLIER REPORT'), 'titleAr' => 'تقرير الموردين',
                'partyLabelEn' => __('Supplier'), 'partyLabelAr' => 'المورد',
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="supplier-report.pdf"']);
        }

        return view('user.reports.suppliers', ['company' => $company, 'period' => $period, 'rows' => $rows] + $summary);
    }

    /**
     * Shared aggregation behind customers()/suppliers(): groups the
     * given documents (Invoices or Bills, already filtered to
     * non-draft/non-void) by their party, and for each one computes
     * this period's activity (document count, invoiced/billed amount,
     * VAT, and actual cash collected/paid — read from the payment
     * model's own paid_at, not the document's cumulative amount_paid,
     * since that isn't period-bound) alongside the party's live
     * outstanding balance across every one of their documents, not
     * just this period's — matching the Aging report's own "always a
     * live snapshot" reasoning (a point-in-time balance needs every
     * payment filtered by its own date too, which isn't worth the
     * complexity here either).
     *
     * @param  class-string  $paymentModel
     */
    private function partyRows($documents, string $paymentModel, string $documentForeignKey, string $partyRelation, array $period)
    {
        $dateField = $partyRelation === 'client' ? 'issue_date' : 'bill_date';
        $documents = $documents->filter(fn ($doc) => $doc->{$partyRelation} !== null);
        $byParty = $documents->groupBy(fn ($doc) => $doc->{$partyRelation}->id);

        $documentPartyIdMap = $documents->mapWithKeys(fn ($doc) => [$doc->id => $doc->{$partyRelation}->id]);
        $payments = $paymentModel::whereIn($documentForeignKey, $documents->pluck('id'))
            ->whereBetween('paid_at', [$period['from'], $period['to']])
            ->get()
            ->groupBy(fn ($p) => $documentPartyIdMap[$p->{$documentForeignKey}] ?? null);

        return $byParty->map(function ($group) use ($payments, $period, $partyRelation, $dateField) {
            $party = $group->first()->{$partyRelation};
            $inPeriod = $group->filter(fn ($doc) => $doc->{$dateField}->betweenIncluded($period['from'], $period['to']));
            $received = (float) ($payments->get($party->id)?->sum('amount') ?? 0);

            return [
                'party' => $party,
                'invoiced' => (float) $inPeriod->sum('total'),
                'vat' => (float) $inPeriod->sum('vat_total'),
                'count' => $inPeriod->count(),
                'received' => $received,
                'outstanding' => round((float) $group->sum(fn ($doc) => $doc->balanceDue()), 2),
                'lastDate' => $group->max($dateField),
            ];
        })->values();
    }

    /**
     * Shared search (by party name) + sort (by any numeric column, or
     * name) for the customers()/suppliers() row collections.
     */
    private function sortAndSearchRows($rows, Request $request, \Closure $nameAccessor)
    {
        if ($request->filled('search')) {
            $search = mb_strtolower((string) $request->query('search'));
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($nameAccessor($row)), $search));
        }

        $sort = $request->query('sort', 'outstanding');
        $direction = $request->query('direction', 'desc');

        return $rows->sortBy(fn ($row) => match ($sort) {
            'name' => $nameAccessor($row),
            'invoiced' => $row['invoiced'],
            'received' => $row['received'],
            'count' => $row['count'],
            default => $row['outstanding'],
        }, SORT_REGULAR, $direction === 'desc')->values();
    }

    /**
     * Who owes the company money, and who the company owes, bucketed by
     * how overdue each outstanding invoice/bill is — the report that was
     * missing next to the existing per-party Account Statement, which
     * shows one party's running balance but never "who's overdue and by
     * how much" across the whole receivables/payables book at a glance.
     * Always a live snapshot (not a historical AS OF date) — a genuine
     * point-in-time aging would need every payment filtered by its own
     * date too, which isn't worth the complexity for a report that's
     * almost always run "as of today" in practice.
     */
    public function aging(Request $request)
    {
        $company = Auth::user()->company;
        $type = $request->query('type', 'receivables') === 'payables' ? 'payables' : 'receivables';

        $rows = $type === 'receivables' ? $this->agedReceivablesRows() : $this->agedPayablesRows();

        $totals = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0, 'total' => 0.0];
        foreach ($rows as $row) {
            foreach ($totals as $bucket => $sum) {
                $totals[$bucket] += $row['buckets'][$bucket] ?? $row['total'];
            }
        }

        if ($request->query('export') === 'csv') {
            $header = [__('Party'), __('Current'), __('1-30 days'), __('31-60 days'), __('61-90 days'), __('90+ days'), __('Total')];
            $csvRows = $rows->map(fn (array $row) => [
                $row['party']->name,
                number_format($row['buckets']['current'], 2, '.', ''),
                number_format($row['buckets']['days_1_30'], 2, '.', ''),
                number_format($row['buckets']['days_31_60'], 2, '.', ''),
                number_format($row['buckets']['days_61_90'], 2, '.', ''),
                number_format($row['buckets']['days_over_90'], 2, '.', ''),
                number_format($row['total'], 2, '.', ''),
            ]);

            return $this->csvResponse('aged-'.$type.'.csv', $header, $csvRows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.aging', [
                'company' => $company, 'type' => $type, 'rows' => $rows, 'totals' => $totals,
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="aged-'.$type.'.pdf"']);
        }

        return view('user.reports.aging', [
            'company' => $company,
            'type' => $type,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{party: Client, buckets: array<string, float>, total: float}>
     */
    private function agedReceivablesRows()
    {
        return Invoice::with('client')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereHas('client')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceDue() > 0.004)
            ->groupBy('client_id')
            ->map(fn ($invoices) => [
                'party' => $invoices->first()->client,
                'buckets' => $this->bucketBalances($invoices, fn (Invoice $i) => $i->due_date ?? $i->issue_date, fn (Invoice $i) => $i->balanceDue()),
                'total' => (float) $invoices->sum(fn (Invoice $i) => $i->balanceDue()),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{party: Supplier, buckets: array<string, float>, total: float}>
     */
    private function agedPayablesRows()
    {
        return Bill::with('supplier')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereHas('supplier')
            ->get()
            ->filter(fn (Bill $bill) => $bill->balanceDue() > 0.004)
            ->groupBy('supplier_id')
            ->map(fn ($bills) => [
                'party' => $bills->first()->supplier,
                'buckets' => $this->bucketBalances($bills, fn (Bill $b) => $b->due_date ?? $b->bill_date, fn (Bill $b) => $b->balanceDue()),
                'total' => (float) $bills->sum(fn (Bill $b) => $b->balanceDue()),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return array<string, float>
     */
    private function bucketBalances($documents, \Closure $dueDate, \Closure $balance): array
    {
        $buckets = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];
        $today = now()->startOfDay();

        foreach ($documents as $document) {
            $amount = $balance($document);
            $due = $dueDate($document);
            $daysOverdue = $due->startOfDay()->lt($today) ? $due->startOfDay()->diffInDays($today) : -1;

            $bucket = match (true) {
                $daysOverdue < 0 => 'current',
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                $daysOverdue <= 90 => 'days_61_90',
                default => 'days_over_90',
            };

            $buckets[$bucket] += $amount;
        }

        return $buckets;
    }

    private function customerStatementLines(?Client $client, array $period): array
    {
        if (! $client) {
            return [collect(), 0.0];
        }

        $lines = collect();
        $openingBalance = 0.0;

        $invoices = $client->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get();
        $payments = InvoicePayment::whereIn('invoice_id', $invoices->pluck('id'))->get();

        foreach ($invoices as $invoice) {
            if ($invoice->issue_date->lt($period['from'])) {
                $openingBalance += $invoice->total;
            } elseif ($invoice->issue_date->lte($period['to'])) {
                $lines->push((object) ['date' => $invoice->issue_date, 'description' => __('Invoice :number', ['number' => $invoice->invoice_number]), 'debit' => $invoice->total, 'credit' => 0]);
            }
        }
        foreach ($payments as $payment) {
            if ($payment->paid_at->lt($period['from'])) {
                $openingBalance -= $payment->amount;
            } elseif ($payment->paid_at->lte($period['to'])) {
                $lines->push((object) ['date' => $payment->paid_at, 'description' => __('Payment received'), 'debit' => 0, 'credit' => $payment->amount]);
            }
        }

        return [$this->runningBalance($lines, $openingBalance), $openingBalance];
    }

    private function supplierStatementLines(?Supplier $supplier, array $period): array
    {
        if (! $supplier) {
            return [collect(), 0.0];
        }

        $lines = collect();
        $openingBalance = 0.0;

        $bills = $supplier->bills()->whereNotIn('status', ['draft', 'void'])->get();
        $payments = BillPayment::whereIn('bill_id', $bills->pluck('id'))->get();

        foreach ($bills as $bill) {
            if ($bill->bill_date->lt($period['from'])) {
                $openingBalance += $bill->total;
            } elseif ($bill->bill_date->lte($period['to'])) {
                $lines->push((object) ['date' => $bill->bill_date, 'description' => __('Bill :number', ['number' => $bill->bill_number]), 'debit' => 0, 'credit' => $bill->total]);
            }
        }
        foreach ($payments as $payment) {
            if ($payment->paid_at->lt($period['from'])) {
                $openingBalance -= $payment->amount;
            } elseif ($payment->paid_at->lte($period['to'])) {
                $lines->push((object) ['date' => $payment->paid_at, 'description' => __('Payment made'), 'debit' => $payment->amount, 'credit' => 0]);
            }
        }

        return [$this->runningBalance($lines, $openingBalance), $openingBalance];
    }

    private function runningBalance($lines, float $openingBalance)
    {
        $running = $openingBalance;

        return $lines->sortBy('date')->values()->map(function ($line) use (&$running) {
            $running += $line->debit - $line->credit;
            $line->balance = $running;

            return $line;
        });
    }
}

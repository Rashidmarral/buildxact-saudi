<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Account;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentVoucher;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Salesperson;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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

        $salesRows = Invoice::with('client', 'warehouse')
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
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

        $outputTax = (float) $salesRows->sum('vat_total');
        $inputTaxPurchases = (float) $purchaseRows->sum('vat_total');
        $expenseTax = (float) $expenseRows->sum('vat_amount');

        $summary = [
            'outputTax' => $outputTax,
            'netSalesExclTax' => (float) $salesRows->sum('subtotal'),
            'inputTaxPurchases' => $inputTaxPurchases,
            'netPurchasesExclTax' => (float) $purchaseRows->sum('subtotal'),
            'expenseTax' => $expenseTax,
            'totalExpenses' => (float) $expenseRows->sum(fn ($e) => $e->gross_amount ?? $e->amount),
            'netTaxPosition' => $outputTax - $inputTaxPurchases - $expenseTax,
        ];

        $summary += $this->vatApportionment($company, $salesRows, $inputTaxPurchases + $expenseTax);
        $summary['netTaxPosition'] = $outputTax - $summary['netRecoverableInputTax'];

        if ($request->query('export') === 'csv') {
            return $this->vatCsvExport($tab, $salesRows, $purchaseRows, $expenseRows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('user.reports.pdf.vat', [
                'company' => $company, 'period' => $period, 'tab' => $tab, 'locale' => App::getLocale(),
                'salesRows' => $salesRows, 'purchaseRows' => $purchaseRows, 'expenseRows' => $expenseRows,
            ] + $summary);

            return $pdf->download('tax-report-'.$tab.'.pdf');
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
        ] + $summary);
    }

    /**
     * A company that only makes taxable (and zero-rated) supplies can
     * recover 100% of its input VAT, which is what netTaxPosition always
     * assumed. A company that also makes VAT-exempt supplies (opted in
     * via Settings > Tax rates) can't recover the portion of its general
     * input VAT attributable to that exempt output — the standard
     * proportional-recovery method applies a recoverable ratio (taxable
     * sales ÷ total sales) to input VAT that isn't directly tied to one
     * specific sale. This app has no per-line attribution data for bills/
     * expenses, so — until that exists — the ratio is applied to all of
     * this period's input VAT (Purchases + Expenses combined), the same
     * simplification most small businesses' bookkeeping already makes.
     */
    private function vatApportionment(Company $company, $salesRows, float $inputVatBeforeApportionment): array
    {
        $inputVatBeforeApportionment = round($inputVatBeforeApportionment, 2);

        if (! $company->vat_makes_exempt_supplies) {
            return [
                'vatApportionmentEnabled' => false,
                'exemptOutputSales' => 0.0,
                'taxableOutputSales' => (float) $salesRows->sum('subtotal'),
                'recoveryPercentage' => 100.0,
                'inputVatBeforeApportionment' => $inputVatBeforeApportionment,
                'nonRecoverableInputTax' => 0.0,
                'netRecoverableInputTax' => $inputVatBeforeApportionment,
            ];
        }

        $exemptOutputSales = (float) InvoiceItem::whereIn('invoice_id', $salesRows->pluck('id'))
            ->whereHas('taxRate', fn ($q) => $q->where('type', TaxRate::TYPE_EXEMPT))
            ->sum('line_total');

        $taxableOutputSales = max(0, (float) $salesRows->sum('subtotal') - $exemptOutputSales);
        $totalOutputSales = $taxableOutputSales + $exemptOutputSales;

        $recoveryPercentage = $company->vat_recovery_percentage !== null
            ? (float) $company->vat_recovery_percentage
            : ($totalOutputSales > 0 ? round(($taxableOutputSales / $totalOutputSales) * 100, 2) : 100.0);

        $netRecoverableInputTax = round($inputVatBeforeApportionment * ($recoveryPercentage / 100), 2);

        return [
            'vatApportionmentEnabled' => true,
            'exemptOutputSales' => $exemptOutputSales,
            'taxableOutputSales' => $taxableOutputSales,
            'recoveryPercentage' => $recoveryPercentage,
            'inputVatBeforeApportionment' => $inputVatBeforeApportionment,
            'nonRecoverableInputTax' => round($inputVatBeforeApportionment - $netRecoverableInputTax, 2),
            'netRecoverableInputTax' => $netRecoverableInputTax,
        ];
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
                [__('Date'), __('Reference'), __('Customer'), __('Tax Number'), __('Net Amount Excl. Tax'), __('Discount'), __('Tax Amount'), __('Total Amount'), __('Payment Status')],
                $salesRows->map(fn (Invoice $i) => [
                    $i->issue_date?->format('Y-m-d'), $i->invoice_number, $i->client->name ?? '', $i->client->vat_number ?? '',
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
        $rows = $this->trialBalanceRows($company, $period);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('trial-balance.csv', [__('Code'), __('Account'), __('Debit'), __('Credit')],
                $rows->map(fn ($r) => [$r['account']->code, $r['account']->name, number_format($r['debit'], 2, '.', ''), number_format($r['credit'], 2, '.', '')]));
        }

        return view('user.reports.trial-balance', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totalDebit' => $rows->sum('debit'),
            'totalCredit' => $rows->sum('credit'),
        ]);
    }

    private function trialBalanceRows($company, array $period)
    {
        return Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($period) {
                $debit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))
                    ->sum('debit');
                $credit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))
                    ->sum('credit');

                return ['account' => $account, 'debit' => $debit, 'credit' => $credit];
            })
            ->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();
    }

    public function balanceSheet(Request $request)
    {
        $company = Auth::user()->company;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();

        $data = $this->balanceSheetData($company, $asOf);

        if ($request->query('export') === 'csv') {
            $rows = collect()
                ->concat($data['assets']->map(fn ($r) => [__('Assets'), $r['account']->code, $r['account']->name, number_format($r['balance'], 2, '.', '')]))
                ->concat($data['liabilities']->map(fn ($r) => [__('Liabilities'), $r['account']->code, $r['account']->name, number_format($r['balance'], 2, '.', '')]))
                ->concat($data['equity']->map(fn ($r) => [__('Equity'), $r['account']->code ?? 'CURRENT_EARNINGS', $r['account']?->name ?? $r['label'], number_format($r['balance'], 2, '.', '')]));

            return $this->csvResponse('balance-sheet.csv', [__('Section'), __('Code'), __('Account'), __('Balance')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('user.reports.pdf.balance-sheet', ['company' => $company, 'asOf' => $asOf] + $data);

            return $pdf->download('balance-sheet.pdf');
        }

        return view('user.reports.balance-sheet', ['company' => $company, 'asOf' => $asOf] + $data);
    }

    private function balanceSheetData($company, Carbon $asOf): array
    {
        $balances = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');
                $balance = $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;

                return ['account' => $account, 'balance' => $balance];
            })
            ->filter(fn ($row) => abs($row['balance']) > 0.005)
            ->groupBy(fn ($row) => $row['account']->type);

        // Retained earnings: without formal period-close entries, net income
        // to date (revenue minus expenses, since inception) is the equity
        // the books imply but never explicitly post — without it, Assets
        // would never actually equal Liabilities + Equity.
        $netIncomeToDate = Account::where('company_id', $company->id)
            ->whereIn('type', ['revenue', 'expense'])
            ->get()
            ->sum(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');

                return $account->type === 'revenue' ? $credit - $debit : -($debit - $credit);
            });

        $equity = $balances->get('equity', collect());
        if (abs($netIncomeToDate) > 0.005) {
            $equity = $equity->push(['account' => null, 'key' => 'CURRENT_EARNINGS', 'label' => __('Current period earnings'), 'balance' => $netIncomeToDate]);
        }

        $assets = $balances->get('asset', collect());
        $liabilities = $balances->get('liability', collect());
        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance');

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ];
    }

    public function incomeStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $lines = function (string $type) use ($company, $period) {
            return Account::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('type', $type)
                ->orderBy('code')
                ->get()
                ->map(function (Account $account) use ($period) {
                    $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('debit');
                    $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('credit');
                    $amount = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

                    return ['account' => $account, 'amount' => $amount];
                })
                ->filter(fn ($row) => abs($row['amount']) > 0.005);
        };

        $revenueLines = $lines('revenue');
        $expenseLines = $lines('expense');

        $netSales = (float) $revenueLines->sum('amount');
        $cogsRow = $expenseLines->first(fn ($r) => $r['account']->code === '5000');
        $cogs = (float) ($cogsRow['amount'] ?? 0);
        $operatingExpenses = (float) $expenseLines->reject(fn ($r) => $r['account']->code === '5000')->sum('amount');
        $grossProfit = $netSales - $cogs;
        $operatingProfit = $grossProfit - $operatingExpenses;
        $netProfit = $operatingProfit;

        if ($request->query('export') === 'csv') {
            $rows = collect()
                ->concat($revenueLines->map(fn ($r) => [__('Revenue'), $r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', '')]))
                ->concat($expenseLines->map(fn ($r) => [__('Expenses'), $r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', '')]));

            return $this->csvResponse('income-statement.csv', [__('Section'), __('Code'), __('Account'), __('Amount')], $rows);
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

        return view('user.reports.sales', [
            'company' => $company,
            'period' => $period,
            'items' => $items,
            'clients' => Client::orderBy('name')->get(),
            'salespersons' => Salesperson::orderBy('name')->get(),
            'products' => Item::orderBy('name')->get(),
            'invoiceCount' => $items->pluck('invoice.id')->unique()->count(),
            'lineCount' => $items->count(),
            'discountTotal' => $items->pluck('invoice')->unique('id')->sum('discount_total'),
            'taxableSales' => $items->sum(fn ($r) => $r->line->quantity * $r->line->unit_price),
            'taxTotal' => $items->sum('line.vat_amount'),
            'totalSales' => $items->sum('line.line_total'),
        ]);
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

        return view('user.reports.expenses', [
            'company' => $company,
            'period' => $period,
            'expenses' => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'total' => $expenses->sum('amount'),
            'vatTotal' => $expenses->sum('vat_amount'),
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

        return view('user.reports.wht-return', [
            'company' => $company,
            'period' => $period,
            'vouchers' => $vouchers,
            'total' => $vouchers->sum('wht_amount'),
        ]);
    }

    public function cashFlow(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $cashAndBankCodes = ['1000', '1100'];

        $rows = Account::where('company_id', $company->id)
            ->whereIn('code', $cashAndBankCodes)
            ->get()
            ->map(function (Account $account) use ($period) {
                $inflow = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('debit');
                $outflow = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('credit');
                $openingDebit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<', $period['from']))->sum('debit');
                $openingCredit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<', $period['from']))->sum('credit');

                return [
                    'account' => $account,
                    'opening' => $openingDebit - $openingCredit,
                    'inflow' => $inflow,
                    'outflow' => $outflow,
                    'closing' => ($openingDebit - $openingCredit) + $inflow - $outflow,
                ];
            });

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('cash-flow.csv', [__('Account'), __('Opening'), __('Cash in'), __('Cash out'), __('Closing')],
                $rows->map(fn ($r) => [$r['account']->name, number_format($r['opening'], 2, '.', ''), number_format($r['inflow'], 2, '.', ''), number_format($r['outflow'], 2, '.', ''), number_format($r['closing'], 2, '.', '')]));
        }

        return view('user.reports.cash-flow', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totalInflow' => $rows->sum('inflow'),
            'totalOutflow' => $rows->sum('outflow'),
            'netChange' => $rows->sum('inflow') - $rows->sum('outflow'),
            'openingTotal' => $rows->sum('opening'),
            'closingTotal' => $rows->sum('closing'),
        ]);
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

        if (in_array($request->query('export'), ['pdf-ar', 'pdf-en']) && $party) {
            $locale = $request->query('export') === 'pdf-ar' ? 'ar' : 'en';
            $previousLocale = App::getLocale();
            App::setLocale($locale);

            $pdf = Pdf::loadView('user.reports.pdf.account-statement', [
                'company' => $company, 'period' => $period, 'party' => $party, 'type' => $type,
                'lines' => $lines, 'openingBalance' => $openingBalance, 'locale' => $locale,
            ]);

            App::setLocale($previousLocale);

            return $pdf->download('account-statement-'.$locale.'.pdf');
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

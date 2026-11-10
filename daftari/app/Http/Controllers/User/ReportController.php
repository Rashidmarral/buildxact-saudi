<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Account;
use App\Models\AdvanceCreditApplication;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PaymentMode;
use App\Models\PaymentVoucher;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\PrepaymentInvoice;
use App\Models\PrepaymentInvoiceApplication;
use App\Models\Project;
use App\Models\ReceiptVoucherAllocation;
use App\Models\Salesperson;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\VatApportionmentService;
use App\Services\MpdfRenderer;
use App\Services\Reports\FinancialReportService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $prepaymentVatAppliedThisPeriod = (float) PrepaymentInvoice::whereHas('appliedToInvoice', fn ($q) => $q->whereIn('id', $salesRows->pluck('id')))->sum('vat_amount')
            + (float) PrepaymentInvoiceApplication::whereIn('invoice_id', $salesRows->pluck('id'))->sum('vat_amount');
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
                'template' => $company->defaultTemplateFor('vat_return'),
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

    /**
     * Hamly-parity redesign: category-grouped (Cash and cash
     * equivalents, Accounts Receivable, ...) with Opening/Transactions/
     * Closing Dr/Cr column-pairs, instead of a flat per-account
     * Debit/Credit-for-the-period list. The plain view here shows only
     * each category's subtotal row; trialBalanceDetailed() below
     * renders the same data down to individual accounts.
     */
    public function trialBalance(Request $request)
    {
        return $this->renderTrialBalance($request, detailed: false);
    }

    public function trialBalanceDetailed(Request $request)
    {
        return $this->renderTrialBalance($request, detailed: true);
    }

    private function renderTrialBalance(Request $request, bool $detailed)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = app(FinancialReportService::class)->trialBalanceGrouped($company, $period['from'], $period['to']);

        $view = $detailed ? 'trial-balance-detailed' : 'trial-balance';
        $routeName = $detailed ? 'app.reports.trial-balance-detailed' : 'app.reports.trial-balance';

        if ($request->query('export') === 'csv') {
            $header = [__('Type'), __('Category'), __('Code'), __('Account'), __('Opening Dr'), __('Opening Cr'), __('Dr'), __('Cr'), __('Closing Dr'), __('Closing Cr')];
            $rows = collect();
            foreach ($data['types'] as $type) {
                foreach ($type['categories'] as $categoryLabel => $category) {
                    if ($detailed) {
                        foreach ($category['rows'] as $r) {
                            $rows->push([$type['label'], $categoryLabel, $r['account']->code, $r['account']->name,
                                number_format($r['openingDebit'], 2, '.', ''), number_format($r['openingCredit'], 2, '.', ''),
                                number_format($r['periodDebit'], 2, '.', ''), number_format($r['periodCredit'], 2, '.', ''),
                                number_format($r['closingDebit'], 2, '.', ''), number_format($r['closingCredit'], 2, '.', '')]);
                        }
                    } else {
                        $rows->push([$type['label'], $categoryLabel, '', __('Subtotal'),
                            number_format($category['openingDebit'], 2, '.', ''), number_format($category['openingCredit'], 2, '.', ''),
                            number_format($category['periodDebit'], 2, '.', ''), number_format($category['periodCredit'], 2, '.', ''),
                            number_format($category['closingDebit'], 2, '.', ''), number_format($category['closingCredit'], 2, '.', '')]);
                    }
                }
            }

            return $this->csvResponse(($detailed ? 'trial-balance-detailed' : 'trial-balance').'.csv', $header, $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.'.$view, [
                'company' => $company, 'period' => $period,
                'template' => $company->defaultTemplateFor($detailed ? 'trial_balance_detailed' : 'trial_balance'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.($detailed ? 'trial-balance-detailed' : 'trial-balance').'.pdf"']);
        }

        return view('user.reports.'.$view, ['company' => $company, 'period' => $period, 'routeName' => $routeName] + $data);
    }

    /**
     * "add more reports... everything should be trackable in reports" —
     * the full, multi-account General Ledger (every account, opening
     * balance, every line in the period, closing balance), the single
     * most-requested document on a year-end audit PBC list. Distinct
     * from JournalController::ledger(), which only shows one account the
     * user picks and has no export.
     *
     * Hamly-parity gap: this used to have no filters at all and always
     * dumped every account, unbounded. account_ids/cost_center_id/
     * location_id mirror the filter pattern ledgerAccountStatement()
     * already established for a single-account statement.
     */
    public function generalLedger(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $accountIds = collect($request->query('account_ids', []))->map(fn ($id) => (int) $id)->filter();
        $costCenterId = $request->filled('cost_center_id') ? (int) $request->query('cost_center_id') : null;
        $locationId = $request->filled('location_id') ? (int) $request->query('location_id') : null;

        $accounts = app(FinancialReportService::class)->generalLedger($company, $period['from'], $period['to'], $accountIds, $costCenterId, $locationId);
        $allAccounts = Account::where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();
        $costCenters = CostCenter::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        if ($request->query('export') === 'csv') {
            $rows = $accounts->flatMap(function ($row) {
                return collect([[$row['account']->code, $row['account']->name, '', __('Opening balance'), '', '', '', number_format($row['openingBalance'], 2, '.', '')]])
                    ->concat($row['lines']->map(fn ($line) => [
                        $row['account']->code, $row['account']->name, $line->journalEntry->entry_date->format('Y-m-d'),
                        $line->journalEntry->entry_number, $line->journalEntry->description,
                        $line->debit > 0 ? number_format($line->debit, 2, '.', '') : '',
                        $line->credit > 0 ? number_format($line->credit, 2, '.', '') : '',
                        number_format($line->running_balance, 2, '.', ''),
                    ]))
                    ->push([$row['account']->code, $row['account']->name, '', __('Closing balance'), '', '', '', number_format($row['closingBalance'], 2, '.', '')]);
            });

            return $this->csvResponse('general-ledger.csv', [__('Code'), __('Account'), __('Date'), __('Entry'), __('Description'), __('Debit'), __('Credit'), __('Balance')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.general-ledger', [
                'company' => $company, 'period' => $period, 'accounts' => $accounts,
                'template' => $company->defaultTemplateFor('general_ledger'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="general-ledger.pdf"']);
        }

        return view('user.reports.general-ledger', [
            'company' => $company,
            'period' => $period,
            'allAccounts' => $allAccounts,
            'accountIds' => $accountIds,
            'costCenters' => $costCenters,
            'costCenterId' => $costCenterId,
            'branches' => $branches,
            'locationId' => $locationId,
            'accounts' => $accounts,
        ]);
    }

    /**
     * The #2 most-requested document on a year-end audit PBC list: a
     * fixed asset roll-forward (opening NBV + additions − disposals −
     * depreciation = closing NBV), tied to the GL. See
     * FinancialReportService::fixedAssetSchedule()'s own docblock.
     */
    public function fixedAssetSchedule(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $rows = app(FinancialReportService::class)->fixedAssetSchedule($company, $period['from'], $period['to']);

        $totals = [
            'openingNbv' => (float) $rows->sum('openingNbv'),
            'additions' => (float) $rows->sum('additions'),
            'depreciation' => (float) $rows->sum('depreciation'),
            'disposals' => (float) $rows->sum('disposals'),
            'closingNbv' => (float) $rows->sum('closingNbv'),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('fixed-asset-schedule.csv',
                [__('Asset code'), __('Name'), __('Category'), __('Opening NBV'), __('Additions'), __('Depreciation'), __('Disposals'), __('Closing NBV')],
                $rows->map(fn ($r) => [
                    $r['asset']->asset_code, $r['asset']->name, $r['asset']->category,
                    number_format($r['openingNbv'], 2, '.', ''), number_format($r['additions'], 2, '.', ''),
                    number_format($r['depreciation'], 2, '.', ''), number_format($r['disposals'], 2, '.', ''), number_format($r['closingNbv'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.fixed-asset-schedule', [
                'company' => $company, 'period' => $period, 'rows' => $rows, 'totals' => $totals,
                'template' => $company->defaultTemplateFor('fixed_asset_schedule'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="fixed-asset-schedule.pdf"']);
        }

        return view('user.reports.fixed-asset-schedule', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * "add more detailed reports... compare" — an optional `compare`
     * query param (prior_period = prior month-end, prior_year = same
     * date last year) puts a second column of figures next to the
     * current ones, the same "Compare with" every competitor's balance
     * sheet offers. See FinancialReportService::mergeComparison().
     */
    public function balanceSheet(Request $request)
    {
        $company = Auth::user()->company;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();
        $compare = $request->query('compare', 'none');

        $reportService = app(FinancialReportService::class);
        $data = $reportService->balanceSheet($company, $asOf);

        $priorAsOf = $this->comparisonAsOf($asOf, $compare);
        $priorData = $priorAsOf ? $reportService->balanceSheet($company, $priorAsOf) : null;

        if ($priorData) {
            $data['assets'] = $reportService->mergeComparison($data['assets'], $priorData['assets'], 'balance');
            $data['liabilities'] = $reportService->mergeComparison($data['liabilities'], $priorData['liabilities'], 'balance');
            $data['equity'] = $reportService->mergeComparison($data['equity'], $priorData['equity'], 'balance');
            $data['priorTotalAssets'] = $priorData['totalAssets'];
            $data['priorTotalLiabilities'] = $priorData['totalLiabilities'];
            $data['priorTotalEquity'] = $priorData['totalEquity'];
        }

        if ($request->query('export') === 'csv') {
            $rowFor = fn ($r) => $priorData
                ? [$r['account']->code ?? 'CURRENT_EARNINGS', $r['account']?->name ?? $r['label'], number_format($r['balance'], 2, '.', ''), number_format($r['priorAmount'], 2, '.', '')]
                : [$r['account']->code ?? 'CURRENT_EARNINGS', $r['account']?->name ?? $r['label'], number_format($r['balance'], 2, '.', '')];

            $rows = collect()
                ->concat($data['assets']->map(fn ($r) => [__('Assets'), ...$rowFor($r)]))
                ->concat($data['liabilities']->map(fn ($r) => [__('Liabilities'), ...$rowFor($r)]))
                ->concat($data['equity']->map(fn ($r) => [__('Equity'), ...$rowFor($r)]));

            $header = $priorData
                ? [__('Section'), __('Code'), __('Account'), __('Balance'), __('Prior')]
                : [__('Section'), __('Code'), __('Account'), __('Balance')];

            return $this->csvResponse('balance-sheet.csv', $header, $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.balance-sheet', [
                'company' => $company, 'asOf' => $asOf, 'priorAsOf' => $priorAsOf, 'compare' => $compare,
                'template' => $company->defaultTemplateFor('balance_sheet'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="balance-sheet.pdf"']);
        }

        return view('user.reports.balance-sheet', ['company' => $company, 'asOf' => $asOf, 'priorAsOf' => $priorAsOf, 'compare' => $compare] + $data);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array{rows: \Illuminate\Support\Collection, total: float}>
     */
    private function groupBalanceSheetRowsByCategory(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        return $rows
            ->groupBy(fn ($r) => $r['account']?->category ?? ($r['label'] ?? __('Other')))
            ->map(fn ($categoryRows) => ['rows' => $categoryRows->values(), 'total' => (float) $categoryRows->sum('balance')]);
    }

    /**
     * Hamly-parity: the same two-sided ("T-account") Balance Sheet
     * layout Hamly's -detailed route uses — Assets on the left,
     * Liabilities + Equity on the right, account-level rows with their
     * ledger code, a Dr/Cr suffix (Account::drCrSuffix()) instead of a
     * sign, and the self-check line every real T-account balance sheet
     * ends with. A distinct, simpler route from balanceSheet() above
     * (no comparison-period support) since Hamly's own -detailed page
     * doesn't offer one either.
     */
    public function balanceSheetDetailed(Request $request)
    {
        return $this->renderTwoSidedBalanceSheet($request, 'balance-sheet-detailed');
    }

    /**
     * Same two-sided layout, but category-level only (no individual
     * account rows) — Hamly's -summary route.
     */
    public function balanceSheetSummary(Request $request)
    {
        return $this->renderTwoSidedBalanceSheet($request, 'balance-sheet-summary');
    }

    private function renderTwoSidedBalanceSheet(Request $request, string $view)
    {
        $company = Auth::user()->company;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();

        $data = app(FinancialReportService::class)->balanceSheet($company, $asOf);
        $assetCategories = $this->groupBalanceSheetRowsByCategory($data['assets']);
        $liabilityCategories = $this->groupBalanceSheetRowsByCategory($data['liabilities']);
        $equityCategories = $this->groupBalanceSheetRowsByCategory($data['equity']);

        $viewData = [
            'company' => $company, 'asOf' => $asOf,
            'assetCategories' => $assetCategories, 'liabilityCategories' => $liabilityCategories, 'equityCategories' => $equityCategories,
            'totalAssets' => $data['totalAssets'], 'totalLiabilities' => $data['totalLiabilities'], 'totalEquity' => $data['totalEquity'],
            'balanced' => $data['balanced'],
        ];

        if ($request->query('export') === 'csv') {
            $header = [__('Side'), __('Category'), __('Code'), __('Account'), __('Amount')];
            $rows = collect();
            $append = function (string $side, \Illuminate\Support\Collection $categories) use (&$rows, $view) {
                foreach ($categories as $categoryLabel => $category) {
                    if ($view === 'balance-sheet-detailed') {
                        foreach ($category['rows'] as $r) {
                            $rows->push([$side, $categoryLabel, $r['account']?->code ?? 'CURRENT_EARNINGS', $r['account']?->name ?? $r['label'], number_format(abs($r['balance']), 2, '.', '').' '.($r['account']?->drCrSuffix($r['balance']) ?? '')]);
                        }
                    }
                    $rows->push([$side, $categoryLabel, '', __('Subtotal'), number_format(abs($category['total']), 2, '.', '')]);
                }
            };
            $append(__('Assets'), $assetCategories);
            $append(__('Liabilities'), $liabilityCategories);
            $append(__('Equity'), $equityCategories);

            return $this->csvResponse($view.'.csv', $header, $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.'.$view, $viewData + ['template' => $company->defaultTemplateFor($view === 'balance-sheet-detailed' ? 'balance_sheet_detailed' : 'balance_sheet_summary')]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$view.'.pdf"']);
        }

        return view('user.reports.'.$view, $viewData);
    }

    /**
     * "add more detailed reports... compare" — an optional `compare`
     * query param (prior_period / prior_year) puts last period's or
     * last year's figures next to the current ones on every line, the
     * same "Compare with" every competitor's P&L offers. Kept entirely
     * separate from priorPeriodAdjustments (a different, pre-existing
     * concept: a reversal landing in this period whose own original
     * entry was dated in an earlier one) — this is a side-by-side
     * comparison of two whole periods, not a same-period breakdown.
     */
    public function incomeStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $compare = $request->query('compare', 'none');

        $reportService = app(FinancialReportService::class);
        $statement = $reportService->incomeStatement($company, $period['from'], $period['to']);
        $revenueLines = $statement['revenueLines'];
        $expenseLines = $statement['expenseLines'];
        $netSales = $statement['netSales'];
        $grossProfit = $statement['grossProfit'];
        $operatingProfit = $statement['operatingProfit'];
        $netProfit = $statement['netProfit'];
        $priorPeriodAdjustments = $statement['priorPeriodAdjustments'];

        $comparisonPeriod = $this->comparisonPeriod($period, $compare);
        $priorStatement = $comparisonPeriod ? $reportService->incomeStatement($company, $comparisonPeriod['from'], $comparisonPeriod['to']) : null;

        if ($priorStatement) {
            $revenueLines = $reportService->mergeComparison($revenueLines, $priorStatement['revenueLines'], 'amount');
            $expenseLines = $reportService->mergeComparison($expenseLines, $priorStatement['expenseLines'], 'amount');
        }

        if ($request->query('export') === 'csv') {
            $rowFor = fn ($r) => $priorStatement
                ? [$r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', ''), number_format($r['priorAmount'], 2, '.', '')]
                : [$r['account']->code, $r['account']->name, number_format($r['amount'], 2, '.', '')];

            $rows = collect()
                ->concat($revenueLines->map(fn ($r) => [__('Revenue'), ...$rowFor($r)]))
                ->concat($expenseLines->map(fn ($r) => [__('Expenses'), ...$rowFor($r)]))
                ->concat($priorPeriodAdjustments->map(fn ($r) => [__('Prior-period adjustment'), $r['entry']->entry_number, $r['label'], number_format($r['amount'], 2, '.', '')]));

            $header = $priorStatement
                ? [__('Section'), __('Code'), __('Account'), __('Amount'), __('Prior')]
                : [__('Section'), __('Code'), __('Account'), __('Amount')];

            return $this->csvResponse('income-statement.csv', $header, $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.income-statement', [
                'company' => $company, 'period' => $period, 'revenueLines' => $revenueLines, 'expenseLines' => $expenseLines,
                'netSales' => $netSales, 'grossProfit' => $grossProfit, 'operatingProfit' => $operatingProfit, 'netProfit' => $netProfit,
                'priorPeriodAdjustments' => $priorPeriodAdjustments,
                'compare' => $compare, 'comparisonPeriod' => $comparisonPeriod, 'priorStatement' => $priorStatement,
                'template' => $company->defaultTemplateFor('income_statement'),
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
            'compare' => $compare,
            'comparisonPeriod' => $comparisonPeriod,
            'priorStatement' => $priorStatement,
            'priorPeriodAdjustments' => $priorPeriodAdjustments,
        ]);
    }

    /**
     * "add more reports... Profit & Loss by month" — Income Statement
     * with one column per calendar month, to see trend/seasonality a
     * single period total can't show.
     */
    public function incomeStatementByMonth(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = app(FinancialReportService::class)->incomeStatementByMonth($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            $monthKeys = $data['months']->map(fn ($m) => $m->format('Y-m'));
            $header = array_merge([__('Account')], $monthKeys->map(fn ($k) => $k)->all(), [__('Total')]);

            $rowFor = fn ($r, $label) => array_merge([$label], $monthKeys->map(fn ($k) => number_format($r['monthly'][$k], 2, '.', ''))->all(), [number_format($r['total'], 2, '.', '')]);

            $rows = collect()
                ->concat($data['revenueLines']->map(fn ($r) => $rowFor($r, $r['account']->name)))
                ->concat([array_merge([__('TOTAL INCOME')], $monthKeys->map(fn ($k) => number_format($data['monthlyIncome'][$k], 2, '.', ''))->all(), [number_format($data['totalIncome'], 2, '.', '')])])
                ->concat($data['expenseLines']->map(fn ($r) => $rowFor($r, $r['account']->name)))
                ->concat([array_merge([__('TOTAL EXPENSES')], $monthKeys->map(fn ($k) => number_format($data['monthlyExpenses'][$k], 2, '.', ''))->all(), [number_format($data['totalExpenses'], 2, '.', '')])])
                ->concat([array_merge([__('NET PROFIT')], $monthKeys->map(fn ($k) => number_format($data['monthlyNetProfit'][$k], 2, '.', ''))->all(), [number_format($data['netProfit'], 2, '.', '')])]);

            return $this->csvResponse('profit-and-loss-by-month.csv', $header, $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.income-statement-by-month', [
                'company' => $company, 'period' => $period,
                'template' => $company->defaultTemplateFor('income_statement_by_month'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="profit-and-loss-by-month.pdf"']);
        }

        return view('user.reports.income-statement-by-month', ['company' => $company, 'period' => $period] + $data);
    }

    /**
     * The fourth standard financial statement a full year-end audit
     * expects — see FinancialReportService::statementOfChangesInEquity()'s
     * own docblock.
     */
    public function statementOfChangesInEquity(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = app(FinancialReportService::class)->statementOfChangesInEquity($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            $rows = $data['rows']->map(fn ($r) => [$r['account']->code, $r['account']->name, number_format($r['opening'], 2, '.', ''), number_format($r['movement'], 2, '.', ''), '', number_format($r['closing'], 2, '.', '')]);
            $rows->push([__('Retained earnings'), '', number_format($data['openingRetainedEarnings'], 2, '.', ''), '', number_format($data['netIncomeForPeriod'], 2, '.', ''), number_format($data['openingRetainedEarnings'] + $data['netIncomeForPeriod'], 2, '.', '')]);

            return $this->csvResponse('statement-of-changes-in-equity.csv', [__('Code'), __('Account'), __('Opening'), __('Movement'), __('Net income'), __('Closing')], $rows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.statement-of-changes-in-equity', [
                'company' => $company, 'period' => $period,
                'template' => $company->defaultTemplateFor('statement_of_changes_in_equity'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="statement-of-changes-in-equity.pdf"']);
        }

        return view('user.reports.statement-of-changes-in-equity', ['company' => $company, 'period' => $period] + $data);
    }

    /**
     * "add more reports... every reports section report detail should
     * be exportable" — a single page listing every bank statement
     * reconciled within the period, across every bank/cash account,
     * instead of opening each account's Bank Reconciliation workflow
     * one at a time to see which ones are actually squared off.
     */
    public function bankReconciliationSummary(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $rows = app(FinancialReportService::class)->bankReconciliationSummary($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('bank-reconciliation-summary.csv',
                [__('Bank account'), __('Statement date'), __('Statement balance'), __('Book balance'), __('Difference'), __('Outstanding items'), __('Status')],
                $rows->map(fn (array $r) => [
                    $r['bankAccount']?->name ?? '—', $r['reconciliation']->statement_date->format('Y-m-d'),
                    number_format($r['statementBalance'], 2, '.', ''), number_format($r['bookBalance'], 2, '.', ''), number_format($r['difference'], 2, '.', ''),
                    $r['outstandingCount'], $r['reconciliation']->status,
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.bank-reconciliation-summary', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('bank_reconciliation_summary'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="bank-reconciliation-summary.pdf"']);
        }

        return view('user.reports.bank-reconciliation-summary', ['company' => $company, 'period' => $period, 'rows' => $rows]);
    }

    /**
     * "add more reports... uncleared bank items" — deposits not yet on
     * the bank statement and payments/cheques not yet presented, for
     * one bank/cash account as at a date.
     */
    public function unclearedBankItems(Request $request)
    {
        $company = Auth::user()->company;
        $accounts = BankAccount::where('company_id', $company->id)->orderBy('name')->get();
        $bankAccount = $accounts->firstWhere('id', (int) $request->query('bank_account_id')) ?? $accounts->first();
        $asOf = $request->filled('as_at') ? \Carbon\Carbon::parse($request->query('as_at')) : now();

        $rows = $bankAccount ? app(FinancialReportService::class)->unclearedBankItems($bankAccount, $asOf) : collect();
        $total = (float) $rows->sum('amount');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('uncleared-bank-items.csv',
                [__('Date'), __('Description'), __('Amount')],
                $rows->map(fn (array $r) => [$r['date']->format('Y-m-d'), $r['label'], number_format($r['amount'], 2, '.', '')]));
        }

        if ($request->query('export') === 'pdf' && $bankAccount) {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.uncleared-bank-items', [
                'company' => $company, 'bankAccount' => $bankAccount, 'asOf' => $asOf, 'rows' => $rows, 'total' => $total,
                'template' => $company->defaultTemplateFor('uncleared_bank_items'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="uncleared-bank-items.pdf"']);
        }

        return view('user.reports.uncleared-bank-items', [
            'company' => $company, 'accounts' => $accounts, 'bankAccount' => $bankAccount,
            'asOf' => $asOf, 'rows' => $rows, 'total' => $total,
        ]);
    }

    /**
     * "add more reports... keep in mind final year Audit" — one click
     * that bundles every statement above (Trial Balance, the full
     * General Ledger, Balance Sheet, Income Statement, Cash Flow,
     * Statement of Changes in Equity, Fixed Asset Schedule) plus Aged
     * Receivables/Payables for the chosen period into a single PDF, so
     * handing a year-end audit its evidence pack is one download
     * instead of eight.
     */
    public function auditPack(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        return view('user.reports.audit-pack', ['company' => $company, 'period' => $period]);
    }

    public function auditPackPdf(Request $request)
    {
        $data = $this->auditPackData($request);

        $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.audit-pack', $data + [
            'template' => $data['company']->defaultTemplateFor('audit_pack'),
        ]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="year-end-audit-pack.pdf"']);
    }

    private function auditPackData(Request $request): array
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $service = app(FinancialReportService::class);

        $trialBalanceRows = $service->trialBalance($company, $period['from'], $period['to']);
        $fixedAssetRows = $service->fixedAssetSchedule($company, $period['from'], $period['to']);
        $receivables = $this->agedReceivablesRows();
        $payables = $this->agedPayablesRows();
        $bucketTotals = fn ($rows) => collect(['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_over_90'])
            ->mapWithKeys(fn ($bucket) => [$bucket => (float) $rows->sum(fn ($r) => $r['buckets'][$bucket])])
            ->put('total', (float) $rows->sum('total'))
            ->all();

        return [
            'company' => $company,
            'period' => $period,
            'trialBalanceRows' => $trialBalanceRows,
            'trialBalanceTotals' => ['debit' => (float) $trialBalanceRows->sum('debit'), 'credit' => (float) $trialBalanceRows->sum('credit')],
            'generalLedgerAccounts' => $service->generalLedger($company, $period['from'], $period['to']),
            'balanceSheet' => $service->balanceSheet($company, $period['to']),
            'incomeStatement' => $service->incomeStatement($company, $period['from'], $period['to']),
            'cashFlow' => $service->cashFlow($company, $period['from'], $period['to']),
            'equity' => $service->statementOfChangesInEquity($company, $period['from'], $period['to']),
            'fixedAssetRows' => $fixedAssetRows,
            'fixedAssetTotals' => [
                'openingNbv' => (float) $fixedAssetRows->sum('openingNbv'),
                'additions' => (float) $fixedAssetRows->sum('additions'),
                'depreciation' => (float) $fixedAssetRows->sum('depreciation'),
                'disposals' => (float) $fixedAssetRows->sum('disposals'),
                'closingNbv' => (float) $fixedAssetRows->sum('closingNbv'),
            ],
            'receivables' => $receivables,
            'receivablesTotals' => $bucketTotals($receivables),
            'payables' => $payables,
            'payablesTotals' => $bucketTotals($payables),
        ];
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
                'template' => $company->defaultTemplateFor('sales_report'),
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

    /**
     * "Hamly has a combined 'Purchase & Sale' summary report. Everything
     * in it is already derivable from your existing reports ... just not
     * combined on one page." Sales (Invoice) and purchase (Bill) totals,
     * finalized documents only (issued invoices, posted bills — a draft
     * was never really part of the books), side by side per month across
     * the selected period, so the two sides of the business are read at
     * a glance instead of flipping between the Sales Report and Bills.
     */
    public function purchaseSaleSummary(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $sales = Invoice::whereBetween('issue_date', [$period['from'], $period['to']])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get(['issue_date', 'total']);

        $purchases = Bill::whereBetween('bill_date', [$period['from'], $period['to']])
            ->where('status', 'posted')
            ->get(['bill_date', 'total']);

        $months = collect();
        for ($cursor = $period['from']->copy()->startOfMonth(); $cursor->lte($period['to']); $cursor->addMonth()) {
            $months->push($cursor->copy());
        }

        $rows = $months->map(function (Carbon $month) use ($sales, $purchases) {
            $salesTotal = $sales->filter(fn ($i) => $i->issue_date->isSameMonth($month) && $i->issue_date->isSameYear($month))->sum('total');
            $purchasesTotal = $purchases->filter(fn ($b) => $b->bill_date->isSameMonth($month) && $b->bill_date->isSameYear($month))->sum('total');

            return [
                'month' => $month,
                'sales' => $salesTotal,
                'purchases' => $purchasesTotal,
                'net' => $salesTotal - $purchasesTotal,
            ];
        });

        $summary = [
            'salesTotal' => $sales->sum('total'),
            'salesCount' => $sales->count(),
            'purchasesTotal' => $purchases->sum('total'),
            'purchasesCount' => $purchases->count(),
        ];
        $summary['net'] = $summary['salesTotal'] - $summary['purchasesTotal'];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('purchase-sale-summary.csv', [__('Month'), __('Sales'), __('Purchases'), __('Net')],
                $rows->map(fn ($r) => [$r['month']->format('Y-m'), number_format($r['sales'], 2, '.', ''), number_format($r['purchases'], 2, '.', ''), number_format($r['net'], 2, '.', '')]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.purchase-sale-summary', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('purchase_sale_summary'),
            ] + $summary);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="purchase-sale-summary.pdf"']);
        }

        return view('user.reports.purchase-sale-summary', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
        ] + $summary);
    }

    /**
     * "i need to track project expenses from operating account" — an
     * operator moving cash from an operating bank account into a
     * separate custody/petty-cash account (both already modeled as
     * BankAccount rows) had no way to see which of those two accounts
     * actually paid a given expense, or to total that split by account.
     * bank_account_id/project_id filters plus a per-account breakdown
     * answer that without needing a new concept — Expense already
     * records which BankAccount it was paid from.
     */
    public function expenses(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $expenses = Expense::with('category', 'bankAccount', 'employee', 'project')
            ->whereBetween('expense_date', [$period['from'], $period['to']])
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->query('category_id')))
            ->when($request->filled('bank_account_id'), fn ($q) => $q->where('bank_account_id', $request->query('bank_account_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('vendor_name', 'like', '%'.$request->query('search').'%')
                ->orWhere('description', 'like', '%'.$request->query('search').'%')))
            ->orderByDesc('expense_date')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('expense-report.csv', [__('Date'), __('Vendor'), __('Category'), __('Description'), __('Account'), __('Project'), __('Amount'), __('VAT')],
                $expenses->map(fn ($e) => [
                    $e->expense_date->format('Y-m-d'), $e->vendor_name, $e->category->name ?? '', $e->description,
                    $this->expenseAccountLabel($e),
                    $e->project?->name ?? '',
                    number_format($e->amount, 2, '.', ''), number_format($e->vat_amount, 2, '.', ''),
                ]));
        }

        $total = $expenses->sum('amount');
        $vatTotal = $expenses->sum('vat_amount');

        // Same amount the table/PDF total row shows, split by whichever
        // account actually paid each expense — exactly "account wise"
        // tracking, so an operating-vs-custody split (or a custodian's
        // own spend) is a glance away instead of re-running the filter
        // once per account.
        $byAccount = $expenses->groupBy(fn (Expense $e) => $e->paid_by_owner ? 'owner' : ($e->employee_id ? 'employee-'.$e->employee_id : ($e->bank_account_id ?: 'unpaid')))
            ->map(fn ($group) => [
                'label' => $this->expenseAccountLabel($group->first()),
                'amount' => $group->sum('amount'),
                'vat_amount' => $group->sum('vat_amount'),
                'count' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.expenses', [
                'company' => $company, 'period' => $period, 'expenses' => $expenses, 'total' => $total, 'vatTotal' => $vatTotal, 'byAccount' => $byAccount,
                'template' => $company->defaultTemplateFor('expense_report'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="expense-report.pdf"']);
        }

        return view('user.reports.expenses', [
            'company' => $company,
            'period' => $period,
            'expenses' => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'total' => $total,
            'vatTotal' => $vatTotal,
            'byAccount' => $byAccount,
        ]);
    }

    /** What actually paid this expense — a real financial account, the owner personally, or an employee's own cash advance (see Expense::employee_id). */
    private function expenseAccountLabel(Expense $expense): string
    {
        if ($expense->paid_by_owner) {
            return __('Owed to owner');
        }

        if ($expense->employee_id) {
            return __(":employee's custody", ['employee' => $expense->employee?->full_name]);
        }

        return $expense->bankAccount?->name ?? __('Unpaid (payable)');
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
                'template' => $company->defaultTemplateFor('wht_return'),
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
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.cash-flow', ['company' => $company, 'period' => $period, 'template' => $company->defaultTemplateFor('cash_flow_statement')] + $data);

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

        // "add if we are missing anything" — what every client statement
        // from a mature accounting system carries beyond a bare ledger:
        // totals for the period shown, which documents are *currently*
        // outstanding (an "open item" view — always as-of-today, not
        // bound to the chosen period, since an overdue invoice from last
        // quarter is still overdue today regardless of what range someone
        // picks to view history for), and the same aging buckets the
        // standalone Aged Receivables/Payables report already uses.
        $totalInvoiced = (float) $lines->sum('debit');
        $totalReceived = (float) $lines->sum('credit');
        $closingBalance = $lines->last()->balance ?? $openingBalance;
        [$outstandingRows, $agingBuckets] = $this->partyOutstandingSummary($party, $type);

        if (in_array($request->query('export'), ['pdf', 'pdf-ar', 'pdf-en']) && $party) {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.account-statement', [
                'company' => $company, 'period' => $period, 'party' => $party, 'type' => $type,
                'lines' => $lines, 'openingBalance' => $openingBalance, 'closingBalance' => $closingBalance,
                'totalInvoiced' => $totalInvoiced, 'totalReceived' => $totalReceived,
                'outstandingRows' => $outstandingRows, 'agingBuckets' => $agingBuckets,
                'template' => $company->defaultTemplateFor('account_statement'),
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
            'closingBalance' => $closingBalance,
            'totalInvoiced' => $totalInvoiced,
            'totalReceived' => $totalReceived,
            'outstandingRows' => $outstandingRows,
            'agingBuckets' => $agingBuckets,
        ]);
    }

    /**
     * "add more reports... Receivables/Payables by contact" — every
     * customer's or supplier's own balance on the receivable/payable
     * control account, with opening, movement and closing for the
     * period — distinct from the Aged Receivables/Payables report
     * (which only ever shows today's outstanding balance, bucketed by
     * how overdue it is, never a period's opening/movement/closing).
     * Reuses the exact same customerStatementLines()/
     * supplierStatementLines() reconstruction the single-party Account
     * Statement already relies on, just run across every party instead
     * of one at a time, so the two reports can never quietly disagree.
     */
    public function receivablesPayablesByContact(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $type = $request->query('type', 'receivables') === 'payables' ? 'payables' : 'receivables';

        $rows = $type === 'receivables'
            ? Client::where('company_id', $company->id)->orderBy('name')->get()->map(fn (Client $client) => $this->contactBalanceRow($client, $this->customerStatementLines($client, $period)))
            : Supplier::where('company_id', $company->id)->orderBy('name')->get()->map(fn (Supplier $supplier) => $this->contactBalanceRow($supplier, $this->supplierStatementLines($supplier, $period)));

        $rows = $rows->filter(fn (array $r) => abs($r['opening']) > 0.005 || abs($r['debit']) > 0.005 || abs($r['credit']) > 0.005)->values();

        $totals = [
            'opening' => (float) $rows->sum('opening'), 'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'), 'closing' => (float) $rows->sum('closing'),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('receivables-payables-by-contact-'.$type.'.csv',
                [__('Contact'), __('Opening balance'), __('Debit'), __('Credit'), __('Closing balance')],
                $rows->map(fn (array $r) => [
                    $r['party']->name, number_format($r['opening'], 2, '.', ''),
                    number_format($r['debit'], 2, '.', ''), number_format($r['credit'], 2, '.', ''), number_format($r['closing'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.receivables-payables-by-contact', [
                'company' => $company, 'period' => $period, 'type' => $type, 'rows' => $rows, 'totals' => $totals,
                'template' => $company->defaultTemplateFor('receivables_payables_by_contact'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="receivables-payables-by-contact-'.$type.'.pdf"']);
        }

        return view('user.reports.receivables-payables-by-contact', [
            'company' => $company, 'period' => $period, 'type' => $type, 'rows' => $rows, 'totals' => $totals,
        ]);
    }

    /**
     * @param array{0: \Illuminate\Support\Collection, 1: float} $statement
     * @return array{party: Client|Supplier, opening: float, debit: float, credit: float, closing: float}
     */
    private function contactBalanceRow(Client|Supplier $party, array $statement): array
    {
        [$lines, $opening] = $statement;
        $debit = (float) $lines->sum('debit');
        $credit = (float) $lines->sum('credit');

        return ['party' => $party, 'opening' => $opening, 'debit' => $debit, 'credit' => $credit, 'closing' => $opening + $debit - $credit];
    }

    /**
     * "add more reports... Bank/Cash Account Statement" — money in and
     * out of ONE bank or cash account for a period, straight from its
     * own ledger: a dedicated, bank-account-picker version of the
     * single-account Ledger (JournalController::ledger()) that only
     * ever offers bank/cash accounts, not the full chart of accounts.
     */
    public function bankAccountStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $bankAccounts = BankAccount::where('company_id', $company->id)->orderBy('name')->get();
        $bankAccount = $bankAccounts->firstWhere('id', (int) $request->query('bank_account_id')) ?? $bankAccounts->first();

        $lines = collect();
        $openingBalance = 0.0;
        $glAccount = $bankAccount?->account;

        if ($glAccount) {
            $query = $glAccount->journalEntryLines()->with('journalEntry');

            $openingDebit = (clone $query)->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $period['from']->toDateString()))->sum('debit');
            $openingCredit = (clone $query)->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $period['from']->toDateString()))->sum('credit');
            $openingBalance = $glAccount->normal_balance === 'debit' ? $openingDebit - $openingCredit : $openingCredit - $openingDebit;

            $query->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]));
            $lines = $query->get()->sortBy(fn ($l) => $l->journalEntry->entry_date.'-'.$l->id);

            $running = $openingBalance;
            $lines = $lines->map(function ($line) use (&$running, $glAccount) {
                $running += $glAccount->normal_balance === 'debit' ? ($line->debit - $line->credit) : ($line->credit - $line->debit);
                $line->running_balance = $running;

                return $line;
            });
        }

        $closingBalance = $lines->isNotEmpty() ? $lines->last()->running_balance : $openingBalance;

        if ($request->query('export') === 'csv' && $bankAccount) {
            $rows = collect([['', __('Opening balance'), '', '', number_format($openingBalance, 2, '.', '')]])
                ->concat($lines->map(fn ($l) => [
                    $l->journalEntry->entry_date->format('Y-m-d'), $l->journalEntry->description,
                    $l->debit > 0 ? number_format($l->debit, 2, '.', '') : '', $l->credit > 0 ? number_format($l->credit, 2, '.', '') : '',
                    number_format($l->running_balance, 2, '.', ''),
                ]));

            return $this->csvResponse('bank-account-statement-'.Str::slug($bankAccount->name).'.csv', [__('Date'), __('Description'), __('Debit'), __('Credit'), __('Balance')], $rows);
        }

        if ($request->query('export') === 'pdf' && $bankAccount) {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.bank-account-statement', [
                'company' => $company, 'period' => $period, 'bankAccount' => $bankAccount,
                'lines' => $lines, 'openingBalance' => $openingBalance, 'closingBalance' => $closingBalance,
                'template' => $company->defaultTemplateFor('bank_account_statement'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="bank-account-statement.pdf"']);
        }

        return view('user.reports.bank-account-statement', [
            'company' => $company, 'period' => $period, 'bankAccounts' => $bankAccounts, 'bankAccount' => $bankAccount,
            'lines' => $lines, 'openingBalance' => $openingBalance, 'closingBalance' => $closingBalance,
        ]);
    }

    /**
     * "add more reports... journal export (all postings)" — every
     * posting line in the period, flat, with every dimension this
     * ledger can attach to it — for an auditor or an external
     * accountant who wants the raw data, not a formatted statement.
     */
    public function journalExport(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $rows = app(FinancialReportService::class)->journalExport($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('journal-export.csv',
                [__('Date'), __('Reference No'), __('Source'), __('Code'), __('Account'), __('Description'), __('Debit'), __('Credit'), __('Cost centre'), __('Contact'), __('Location'), __('User')],
                $rows->map(fn (array $r) => [
                    $r['date']->format('Y-m-d'), $r['reference'], $r['source'], $r['code'], $r['account'], $r['description'],
                    $r['debit'] > 0 ? number_format($r['debit'], 2, '.', '') : '', $r['credit'] > 0 ? number_format($r['credit'], 2, '.', '') : '',
                    $r['costCentre'], $r['contact'], $r['location'], $r['user'],
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.journal-export', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'totalDebit' => (float) $rows->sum('debit'), 'totalCredit' => (float) $rows->sum('credit'),
                'template' => $company->defaultTemplateFor('journal_export'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="journal-export.pdf"']);
        }

        return view('user.reports.journal-export', [
            'company' => $company, 'period' => $period, 'rows' => $rows,
            'totalDebit' => (float) $rows->sum('debit'), 'totalCredit' => (float) $rows->sum('credit'),
        ]);
    }

    /**
     * "add more reports... Account Statement" filtered by "location,
     * transaction type, user and currency" — a statement of ONE OR
     * MORE ledger accounts at once (vs JournalController::ledger(),
     * which only ever offers one), narrowable by source type and user.
     * See FinancialReportService::accountStatementMulti()'s own
     * docblock for why there's no location filter.
     */
    public function ledgerAccountStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $accounts = Account::where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();
        $accountIds = collect($request->query('account_ids', []))->map(fn ($id) => (int) $id)->filter();
        $sourceType = $request->filled('source_type') ? $request->query('source_type') : null;
        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;

        $rows = $accountIds->isNotEmpty()
            ? app(FinancialReportService::class)->accountStatementMulti($company, $accountIds, $period['from'], $period['to'], $sourceType, $userId)
            : collect();

        $sourceTypes = $company->journalEntries()->distinct()->pluck('source_type');
        $users = User::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        if ($request->query('export') === 'csv') {
            $rowsCsv = collect();
            foreach ($rows as $row) {
                $rowsCsv->push([$row['account']->code, $row['account']->name, __('Opening balance'), '', '', number_format($row['openingBalance'], 2, '.', '')]);
                foreach ($row['lines'] as $line) {
                    $rowsCsv->push([
                        $row['account']->code, $row['account']->name, $line->journalEntry->entry_date->format('Y-m-d'),
                        $line->debit > 0 ? number_format($line->debit, 2, '.', '') : '', $line->credit > 0 ? number_format($line->credit, 2, '.', '') : '',
                        number_format($line->running_balance, 2, '.', ''),
                    ]);
                }
            }

            return $this->csvResponse('ledger-account-statement.csv', [__('Code'), __('Account'), __('Date'), __('Debit'), __('Credit'), __('Balance')], $rowsCsv);
        }

        if ($request->query('export') === 'pdf' && $rows->isNotEmpty()) {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.ledger-account-statement', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('ledger_account_statement'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ledger-account-statement.pdf"']);
        }

        return view('user.reports.ledger-account-statement', [
            'company' => $company, 'period' => $period, 'accounts' => $accounts, 'accountIds' => $accountIds,
            'sourceTypes' => $sourceTypes, 'users' => $users, 'sourceType' => $sourceType, 'userId' => $userId, 'rows' => $rows,
        ]);
    }

    /**
     * "add more reports... Profit & Loss by cost centre" — see
     * FinancialReportService::profitLossByCostCentre()'s own docblock
     * for what counts toward a cost centre today and why most lines
     * currently land in "Unassigned".
     */
    public function profitLossByCostCentre(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = app(FinancialReportService::class)->profitLossByCostCentre($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('profit-and-loss-by-cost-centre.csv',
                [__('Cost centre'), __('Income'), __('Expenses'), __('Net')],
                $data['rows']->map(fn (array $r) => [
                    $r['costCentre']?->label() ?? __('Unassigned'),
                    number_format($r['income'], 2, '.', ''), number_format($r['expenses'], 2, '.', ''), number_format($r['net'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.profit-loss-by-cost-centre', [
                'company' => $company, 'period' => $period,
                'template' => $company->defaultTemplateFor('profit_loss_by_cost_centre'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="profit-and-loss-by-cost-centre.pdf"']);
        }

        return view('user.reports.profit-loss-by-cost-centre', ['company' => $company, 'period' => $period] + $data);
    }

    /**
     * "add more reports... Profit & Loss by location" — see
     * FinancialReportService::profitLossByLocation()'s own docblock for
     * how a line's branch is derived and why most non-invoice/bill
     * postings land in "Unassigned".
     */
    public function profitLossByLocation(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = app(FinancialReportService::class)->profitLossByLocation($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('profit-and-loss-by-location.csv',
                [__('Location'), __('Income'), __('Expenses'), __('Net')],
                $data['rows']->map(fn (array $r) => [
                    $r['branch']?->name ?? __('Unassigned'),
                    number_format($r['income'], 2, '.', ''), number_format($r['expenses'], 2, '.', ''), number_format($r['net'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.profit-loss-by-location', [
                'company' => $company, 'period' => $period,
                'template' => $company->defaultTemplateFor('profit_loss_by_location'),
            ] + $data);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="profit-and-loss-by-location.pdf"']);
        }

        return view('user.reports.profit-loss-by-location', ['company' => $company, 'period' => $period] + $data);
    }

    /**
     * "add more... Payment Mode Statement" — see FinancialReportService
     * ::paymentModeStatement()'s own doc block for what counts as a
     * payment mode's own transaction and why a voided sale's payments
     * are excluded.
     */
    public function paymentModeStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        PaymentMode::seedDefaults($company->id);
        $modes = PaymentMode::where('company_id', $company->id)->orderBy('sort_order')->orderBy('name')->get();
        $modeId = $request->query('payment_mode_id') ? (int) $request->query('payment_mode_id') : null;
        $rows = app(FinancialReportService::class)->paymentModeStatement($company, $period['from'], $period['to'], $modeId);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('payment-mode-statement.csv',
                [__('Date'), __('Sale'), __('Mode'), __('Amount'), __('Reference'), __('Running total')],
                $rows->map(fn (array $r) => [
                    $r['date']->format('Y-m-d'), $r['saleNumber'], $r['mode'],
                    number_format($r['amount'], 2, '.', ''), $r['reference'], number_format($r['runningTotal'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.payment-mode-statement', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('payment_mode_statement'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="payment-mode-statement.pdf"']);
        }

        return view('user.reports.payment-mode-statement', ['company' => $company, 'period' => $period, 'modes' => $modes, 'modeId' => $modeId, 'rows' => $rows]);
    }

    /**
     * "add more... Payment Mode Balances" — see FinancialReportService
     * ::paymentModeBalances()'s own doc block.
     */
    public function paymentModeBalances(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        PaymentMode::seedDefaults($company->id);
        $rows = app(FinancialReportService::class)->paymentModeBalances($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('payment-mode-balances.csv',
                [__('Payment mode'), __('Collected this period'), __('Account balance')],
                $rows->map(fn (array $r) => [
                    $r['mode']->name, number_format($r['periodTotal'], 2, '.', ''),
                    $r['accountBalance'] === null ? '' : number_format($r['accountBalance'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.payment-mode-balances', [
                'company' => $company, 'period' => $period, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('payment_mode_balances'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="payment-mode-balances.pdf"']);
        }

        return view('user.reports.payment-mode-balances', ['company' => $company, 'period' => $period, 'rows' => $rows]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: array<string, float>}
     */
    private function partyOutstandingSummary(Client|Supplier|null $party, string $type): array
    {
        $empty = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];

        if (! $party) {
            return [collect(), $empty];
        }

        if ($type === 'customer') {
            $docs = $party->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get()->filter(fn (Invoice $i) => $i->balanceDue() > 0.004);
            $rows = $docs->map(fn (Invoice $i) => ['number' => $i->invoice_number, 'date' => $i->issue_date, 'due_date' => $i->due_date, 'total' => (float) $i->total, 'balance' => $i->balanceDue()])->values();
            $buckets = $this->bucketBalances($docs, fn (Invoice $i) => $i->due_date ?? $i->issue_date, fn (Invoice $i) => $i->balanceDue());
        } else {
            $docs = $party->bills()->whereNotIn('status', ['draft', 'void'])->get()->filter(fn (Bill $b) => $b->balanceDue() > 0.004);
            $rows = $docs->map(fn (Bill $b) => ['number' => $b->bill_number, 'date' => $b->bill_date, 'due_date' => $b->due_date, 'total' => (float) $b->total, 'balance' => $b->balanceDue()])->values();
            $buckets = $this->bucketBalances($docs, fn (Bill $b) => $b->due_date ?? $b->bill_date, fn (Bill $b) => $b->balanceDue());
        }

        return [$rows->sortBy('due_date')->values(), $buckets];
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
                'template' => $company->defaultTemplateFor('customer_report'),
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
                'template' => $company->defaultTemplateFor('supplier_report'),
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
                'template' => $company->defaultTemplateFor('aging_report'),
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
     * "add more reports... Account Receivable/Payable Ageing Details" —
     * the bucketed aging() report above answers "how much is overdue
     * per customer"; this answers "which invoices exactly", one row
     * per still-open invoice/bill instead of one row per party.
     */
    public function agingDetails(Request $request)
    {
        $company = Auth::user()->company;
        $type = $request->query('type', 'receivables') === 'payables' ? 'payables' : 'receivables';

        $rows = $type === 'receivables' ? $this->agedReceivablesDetailRows() : $this->agedPayablesDetailRows();

        if ($request->query('export') === 'csv') {
            $header = $type === 'receivables'
                ? [__('Date'), __('Transaction Type'), __('Invoice No.'), __('Customer'), __('Due Date'), __('Due')]
                : [__('Date'), __('Transaction Type'), __('Reference No'), __('Supplier'), __('Due Date'), __('Due')];

            $csvRows = $rows->map(fn (array $r) => [
                $r['date']->format('Y-m-d'), $r['transactionType'], $r['reference'], $r['party'],
                $r['dueDate']->format('Y-m-d'), number_format($r['due'], 2, '.', ''),
            ]);

            return $this->csvResponse('aged-'.$type.'-details.csv', $header, $csvRows);
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.aging-details', [
                'company' => $company, 'type' => $type, 'rows' => $rows,
                'totalDue' => (float) $rows->sum('due'),
                'template' => $company->defaultTemplateFor('aging_details_report'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="aged-'.$type.'-details.pdf"']);
        }

        return view('user.reports.aging-details', [
            'company' => $company,
            'type' => $type,
            'rows' => $rows,
            'totalDue' => (float) $rows->sum('due'),
        ]);
    }

    /**
     * Audit finding CASH-02: every active employee's running cash-advance
     * balance — what they've been given via Payment Vouchers, minus what
     * they've repaid via Receipt Vouchers, minus what they've actually
     * spent on real, itemized company expenses (Expense::employee_id —
     * see LedgerPostingService::postExpense()). "spent" previously
     * wasn't tracked at all: an advance Khalid genuinely spent on
     * materials still showed as fully outstanding, as if he still held
     * all of it in cash. A running balance, not a period report, since
     * an advance outstanding from last quarter is still owed today
     * regardless of what date range someone picks.
     */
    public function employeeAdvances(Request $request)
    {
        $company = Auth::user()->company;

        $rows = Employee::query()
            ->withSum(['paymentVouchers as advanced_sum' => fn ($q) => $q->where('status', 'issued')], 'amount')
            ->withSum(['receiptVouchers as settled_sum' => fn ($q) => $q->where('status', 'issued')], 'amount')
            ->withSum('expenses as spent_sum', 'gross_amount')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'employee' => $employee,
                'advanced' => (float) $employee->advanced_sum,
                'settled' => (float) $employee->settled_sum,
                'spent' => (float) $employee->spent_sum,
                'outstanding' => round((float) $employee->advanced_sum - (float) $employee->settled_sum - (float) $employee->spent_sum, 2),
            ])
            ->filter(fn (array $row) => $row['advanced'] > 0 || $row['settled'] > 0 || $row['spent'] > 0)
            ->values();

        $summary = [
            'totalAdvanced' => $rows->sum('advanced'),
            'totalSettled' => $rows->sum('settled'),
            'totalSpent' => $rows->sum('spent'),
            'totalOutstanding' => $rows->sum('outstanding'),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('employee-advances.csv',
                [__('Employee'), __('Employee No.'), __('Advanced'), __('Settled'), __('Spent'), __('Outstanding')],
                $rows->map(fn (array $r) => [
                    $r['employee']->full_name, $r['employee']->employee_number,
                    number_format($r['advanced'], 2, '.', ''), number_format($r['settled'], 2, '.', ''),
                    number_format($r['spent'], 2, '.', ''), number_format($r['outstanding'], 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = app(MpdfRenderer::class)->render('user.reports.pdf.employee-advances', [
                'company' => $company, 'rows' => $rows,
                'template' => $company->defaultTemplateFor('employee_advances'),
            ] + $summary);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="employee-advances.pdf"']);
        }

        return view('user.reports.employee-advances', ['company' => $company, 'rows' => $rows] + $summary);
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
     * @return \Illuminate\Support\Collection<int, array{date: \Carbon\Carbon, transactionType: string, reference: string, party: string, dueDate: \Carbon\Carbon, due: float}>
     */
    private function agedReceivablesDetailRows()
    {
        return Invoice::with('client')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereHas('client')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceDue() > 0.004)
            ->map(fn (Invoice $i) => [
                'date' => $i->issue_date,
                'transactionType' => __('Invoice'),
                'reference' => $i->invoice_number,
                'party' => $i->client->name,
                'dueDate' => $i->due_date ?? $i->issue_date,
                'due' => (float) $i->balanceDue(),
            ])
            ->sortBy('dueDate')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{date: \Carbon\Carbon, transactionType: string, reference: string, party: string, dueDate: \Carbon\Carbon, due: float}>
     */
    private function agedPayablesDetailRows()
    {
        return Bill::with('supplier')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereHas('supplier')
            ->get()
            ->filter(fn (Bill $bill) => $bill->balanceDue() > 0.004)
            ->map(fn (Bill $b) => [
                'date' => $b->bill_date,
                'transactionType' => __('Bill'),
                'reference' => $b->bill_number,
                'party' => $b->supplier->name,
                'dueDate' => $b->due_date ?? $b->bill_date,
                'due' => (float) $b->balanceDue(),
            ])
            ->sortBy('dueDate')
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
        $invoiceIds = $invoices->pluck('id');

        // "there is one transfer reference how can i deal with it" — a
        // single real deposit split across several invoices (see
        // ReceiptVoucherAllocation) must still read back as the one real
        // payment the client actually sent, not as several smaller lines
        // that don't match their own bank statement. Pull those out
        // first so the plain per-InvoicePayment loop below never
        // double-lists them.
        $allocations = ReceiptVoucherAllocation::with('invoicePayment', 'invoice', 'receiptVoucher')
            ->whereNotNull('invoice_payment_id')
            ->whereHas('invoice', fn ($q) => $q->whereIn('id', $invoiceIds))
            ->get();
        $allocatedPaymentIds = $allocations->pluck('invoice_payment_id');

        $payments = InvoicePayment::with('invoice')
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotIn('id', $allocatedPaymentIds)
            ->get();

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
                $lines->push((object) ['date' => $payment->paid_at, 'description' => $this->paymentDescription(__('Payment received'), $payment->invoice?->invoice_number, $payment->reference), 'debit' => 0, 'credit' => $payment->amount]);
            }
        }
        foreach ($allocations->groupBy('receipt_voucher_id') as $group) {
            $voucher = $group->first()->receiptVoucher;
            $date = $group->first()->invoicePayment->paid_at;
            $amount = $group->sum('amount');
            $invoiceNumbers = $group->map(fn (ReceiptVoucherAllocation $a) => $a->invoice?->invoice_number)->filter()->implode(', ');

            if ($date->lt($period['from'])) {
                $openingBalance -= $amount;
            } elseif ($date->lte($period['to'])) {
                $lines->push((object) ['date' => $date, 'description' => $this->paymentDescription(__('Payment received'), $invoiceNumbers, $voucher?->reference), 'debit' => 0, 'credit' => $amount]);
            }
        }

        // A credit note, an Advance Credit drawn down against an invoice,
        // or a Prepayment Invoice applied to one all reduce that
        // invoice's own balanceDue() exactly like a payment does — any
        // one of them existing without a matching ledger line here would
        // leave this statement's own closing balance disagreeing with
        // the sum of every invoice's own (already correct) balance due.
        foreach (CreditNote::where('client_id', $client->id)->where('status', 'issued')->with('invoice')->get() as $creditNote) {
            if ($creditNote->issue_date->lt($period['from'])) {
                $openingBalance -= $creditNote->total;
            } elseif ($creditNote->issue_date->lte($period['to'])) {
                $lines->push((object) ['date' => $creditNote->issue_date, 'description' => $this->paymentDescription(__('Credit note :number', ['number' => $creditNote->credit_note_number]), $creditNote->invoice?->invoice_number, null), 'debit' => 0, 'credit' => $creditNote->total]);
            }
        }
        foreach (AdvanceCreditApplication::whereIn('invoice_id', $invoiceIds)->with('invoice')->get() as $application) {
            $date = $application->created_at->toImmutable();

            if ($date->lt($period['from'])) {
                $openingBalance -= $application->total;
            } elseif ($date->lte($period['to'])) {
                $lines->push((object) ['date' => $date, 'description' => $this->paymentDescription(__('Advance credit applied'), $application->invoice?->invoice_number, null), 'debit' => 0, 'credit' => $application->total]);
            }
        }
        foreach ($invoices as $invoice) {
            if (! $invoice->appliesPrepaymentInvoice) {
                continue;
            }

            $prepayment = $invoice->appliesPrepaymentInvoice;

            if ($invoice->issue_date->lt($period['from'])) {
                $openingBalance -= $prepayment->total;
            } elseif ($invoice->issue_date->lte($period['to'])) {
                $lines->push((object) ['date' => $invoice->issue_date, 'description' => $this->paymentDescription(__('Prepayment :number applied', ['number' => $prepayment->prepayment_number]), $invoice->invoice_number, null), 'debit' => 0, 'credit' => $prepayment->total]);
            }
        }
        foreach (PrepaymentInvoiceApplication::whereIn('invoice_id', $invoiceIds)->with('invoice', 'prepaymentInvoice')->get() as $application) {
            $date = $application->created_at->toImmutable();

            if ($date->lt($period['from'])) {
                $openingBalance -= $application->total;
            } elseif ($date->lte($period['to'])) {
                $lines->push((object) ['date' => $date, 'description' => $this->paymentDescription(__('Prepayment :number applied', ['number' => $application->prepaymentInvoice?->prepayment_number]), $application->invoice?->invoice_number, null), 'debit' => 0, 'credit' => $application->total]);
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
        $payments = BillPayment::with('bill')->whereIn('bill_id', $bills->pluck('id'))->get();

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
                $lines->push((object) ['date' => $payment->paid_at, 'description' => $this->paymentDescription(__('Payment made'), $payment->bill?->bill_number, $payment->reference), 'debit' => $payment->amount, 'credit' => 0]);
            }
        }

        return [$this->runningBalance($lines, $openingBalance), $openingBalance];
    }

    /**
     * "I need to update account statement to show transaction ... with
     * reference of transaction" — every payment line here used to read
     * as a bare "Payment received"/"Payment made" with no way to tell,
     * from the statement alone, which document it settled or which real
     * bank transfer/cheque it corresponds to. Folds the settled
     * document's number and the payer's own reference (bank transfer
     * ref, cheque number, ...) into the one description column every
     * output (web, PDF, CSV) already renders, so no view template needs
     * to grow a new column for it.
     *
     * "i think we don't need receipt voucher reference" — the internal
     * RV-xxxxx/PV-xxxxx voucher number this previously also appended
     * meant nothing to the client reading their own copy of the
     * statement (it's bookkeeping, not something on their bank
     * statement); the real bank/cheque reference above is what actually
     * lets them recognize their own payment.
     */
    private function paymentDescription(string $label, ?string $documentNumber, ?string $reference): string
    {
        if ($documentNumber) {
            $label .= ' — '.$documentNumber;
        }

        return $reference ? $label.' ('.__('Ref').': '.$reference.')' : $label;
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

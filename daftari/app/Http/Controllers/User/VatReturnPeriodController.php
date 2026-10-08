<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\VatReturnPeriod;
use App\Services\Accounting\VatApportionmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "we have submit vat returns for 2 quarters and third we have paid now
 * so we have already some advance vat ... how to tackle this" — a
 * persisted record per filed VAT period, so an input-VAT credit (input
 * exceeding output — a refundable position, not a loss) is carried
 * forward into the next period instead of being recalculated from
 * scratch with no memory of what came before. This is a bookkeeping aid
 * for preparing the figures — the actual return is still filed on
 * ZATCA's own portal; this just keeps the brought-forward/carried-forward
 * chain straight between quarters.
 */
class VatReturnPeriodController extends Controller
{
    public function index()
    {
        $periods = VatReturnPeriod::with('creator')->orderByDesc('period_end')->paginate(12);

        return view('user.vat-returns.index', compact('periods'));
    }

    public function create(Request $request)
    {
        $company = Auth::user()->company;

        $periodStart = $request->filled('period_start') ? Carbon::parse($request->query('period_start')) : $this->suggestedNextStart($company->id);
        $periodEnd = $request->filled('period_end') ? Carbon::parse($request->query('period_end')) : $periodStart->copy()->addMonths(3)->subDay();

        $summary = $this->summarize($company, $periodStart, $periodEnd->copy()->endOfDay());
        $priorPeriod = VatReturnPeriod::latestBefore($company->id, $periodStart);

        return view('user.vat-returns.create', [
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'summary' => $summary,
            'creditBroughtForward' => $priorPeriod?->credit_carried_forward ?? 0.0,
            'priorPeriod' => $priorPeriod,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'credit_brought_forward' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Auth::user()->company;
        $periodStart = Carbon::parse($data['period_start']);
        $periodEnd = Carbon::parse($data['period_end'])->endOfDay();

        // Every tax figure is always recomputed here from the real
        // posted records for this exact date range — never trusted from
        // the request — the same reasoning as ZakatController::store():
        // a user could otherwise submit any number for the base the
        // payable/carried-forward figures are built on. Only
        // credit_brought_forward is a genuine user input, since periods
        // filed before this feature existed have no prior record to pull
        // it from automatically.
        $summary = $this->summarize($company, $periodStart, $periodEnd);
        $creditBroughtForward = (float) $data['credit_brought_forward'];

        $netBeforeCarryForward = $summary['outputTax'] - $summary['netRecoverableInputTax'];
        $netPosition = $netBeforeCarryForward - $creditBroughtForward;
        $amountPayable = max(0, $netPosition);
        $creditCarriedForward = max(0, -$netPosition);

        $period = $company->vatReturnPeriods()->create([
            'created_by' => Auth::id(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'output_tax' => $summary['outputTax'],
            'input_tax_purchases' => $summary['inputTaxPurchases'],
            'expense_tax' => $summary['expenseTax'],
            'net_recoverable_input_tax' => $summary['netRecoverableInputTax'],
            'credit_brought_forward' => $creditBroughtForward,
            'amount_payable' => $amountPayable,
            'credit_carried_forward' => $creditCarriedForward,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::record('vat_return_period.record', $period, __('Recorded VAT return period :from to :to', ['from' => $period->period_start->format('Y-m-d'), 'to' => $period->period_end->format('Y-m-d')]));

        return redirect()->route('app.reports.vat-returns.show', $period)->with('status', __('VAT return period recorded.'));
    }

    public function show(VatReturnPeriod $vatReturn)
    {
        return view('user.vat-returns.show', ['period' => $vatReturn]);
    }

    public function destroy(VatReturnPeriod $vatReturn)
    {
        $vatReturn->delete();

        return redirect()->route('app.reports.vat-returns.index')->with('status', __('VAT return period deleted.'));
    }

    /**
     * The day after the latest filed period's end, or today's quarter
     * start if nothing has been filed yet — a reasonable default so the
     * create form opens already pointed at "whatever comes next" instead
     * of an arbitrary blank range.
     */
    private function suggestedNextStart(int $companyId): Carbon
    {
        $latest = VatReturnPeriod::where('company_id', $companyId)->orderByDesc('period_end')->first();

        return $latest ? $latest->period_end->copy()->addDay() : now()->firstOfQuarter();
    }

    private function summarize($company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $salesRows = Invoice::where('company_id', $company->id)
            ->whereBetween('issue_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $purchaseRows = Bill::where('company_id', $company->id)
            ->whereBetween('bill_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['draft', 'void'])
            ->get();

        $expenseRows = Expense::where('company_id', $company->id)
            ->whereBetween('expense_date', [$periodStart, $periodEnd])
            ->where('status', '!=', 'rejected')
            ->get();

        $outputTax = (float) $salesRows->sum('vat_total');
        $inputTaxPurchases = (float) $purchaseRows->sum('vat_total');
        $expenseTax = (float) $expenseRows->sum('vat_amount');

        $apportionment = app(VatApportionmentService::class)->calculate($company, $salesRows, $inputTaxPurchases + $expenseTax);

        return [
            'outputTax' => $outputTax,
            'inputTaxPurchases' => $inputTaxPurchases,
            'expenseTax' => $expenseTax,
            'netRecoverableInputTax' => $apportionment['netRecoverableInputTax'],
        ] + $apportionment;
    }
}

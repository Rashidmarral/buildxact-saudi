<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\VatReturnPeriod;
use App\Services\Accounting\VatApportionmentService;
use App\Services\MpdfRenderer;
use App\Support\VatPeriods;
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
 *
 * Reworked to pick the period by quarter/month + year (via VatPeriods,
 * driven by Company::vat_filing_frequency) instead of making the user
 * work out raw from/to dates by hand, and to show which periods are
 * filed, due soon, or overdue — "full control" over VAT the way a
 * modern system offers it, not just a bare date-range report.
 */
class VatReturnPeriodController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $frequency = $company->vat_filing_frequency;

        $periods = VatReturnPeriod::with('creator')->orderByDesc('period_end')->paginate($this->resolvePerPage($request, 12))->withQueryString();

        return view('user.vat-returns.index', [
            'periods' => $periods,
            'frequency' => $frequency,
            'statusRows' => $this->statusRows($company, $frequency),
        ]);
    }

    /**
     * Every period of this year and last year, each tagged against
     * what's actually on file — the "which quarters have I filed"
     * overview a bare list of recorded periods can't show, since it
     * only ever shows what's already there, never what's missing.
     * Bounded to two years so this never grows unbounded for an
     * old company — nothing before that is "due" in any meaningful
     * sense any more.
     */
    private function statusRows($company, string $frequency)
    {
        $now = now();
        $recorded = VatReturnPeriod::where('company_id', $company->id)->get();

        $periods = collect(VatPeriods::periodsForYear($frequency, $now->year - 1))
            ->concat(VatPeriods::periodsForYear($frequency, $now->year));

        return $periods->map(function (array $period) use ($recorded, $now) {
            $match = $recorded->first(fn ($r) => $r->period_start->isSameDay($period['start']) && $r->period_end->isSameDay($period['end']));

            $status = match (true) {
                $match !== null => 'filed',
                VatPeriods::isOverdue($period, $now) => 'overdue',
                VatPeriods::dueInDays($period, $now) <= 15 && $period['start']->lte($now) => 'due_soon',
                $period['start']->gt($now) => 'upcoming',
                default => 'overdue',
            };

            return $period + ['status' => $status, 'recorded' => $match];
        })
            // Upcoming periods more than one step ahead are just noise —
            // nobody needs to see next year's Q4 sitting in this list.
            ->reject(fn ($row) => $row['status'] === 'upcoming' && $row['start']->gt($now->copy()->addDays(45)))
            ->sortByDesc(fn ($row) => $row['start']->timestamp)
            ->values();
    }

    public function create(Request $request)
    {
        $company = Auth::user()->company;
        $frequency = $company->vat_filing_frequency;

        if ($request->query('custom') === '1') {
            $periodStart = $request->filled('period_start') ? Carbon::parse($request->query('period_start')) : $this->suggestedNextStart($company->id, $frequency);
            $periodEnd = $request->filled('period_end') ? Carbon::parse($request->query('period_end')) : $periodStart->copy()->addMonths($frequency === 'monthly' ? 1 : 3)->subDay();
            $selectedPeriod = null;
        } else {
            $year = (int) $request->query('year', 0) ?: null;
            $index = $request->query('index') !== null ? (int) $request->query('index') : null;

            if ($year && $index) {
                $all = VatPeriods::periodsForYear($frequency, $year);
                $selectedPeriod = $all[$index - 1] ?? null;
            } else {
                $selectedPeriod = null;
            }

            if (! $selectedPeriod) {
                $selectedPeriod = VatPeriods::periodAfter($frequency, $this->suggestedNextStart($company->id, $frequency)->copy()->subDay());
            }

            $periodStart = $selectedPeriod['start']->copy();
            $periodEnd = $selectedPeriod['end']->copy();
        }

        $summary = $this->summarize($company, $periodStart, $periodEnd->copy()->endOfDay());
        $priorPeriod = VatReturnPeriod::latestBefore($company->id, $periodStart);

        // A couple of years either side of today is plenty to pick from
        // — nobody is filing a period from 5 years ago through this
        // wizard; that edge case is exactly what "Custom range" is for.
        $selectableYears = [now()->year - 1, now()->year, now()->year + 1];

        return view('user.vat-returns.create', [
            'frequency' => $frequency,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'selectedPeriod' => $selectedPeriod,
            'selectableYears' => $selectableYears,
            'periodsByYear' => collect($selectableYears)->mapWithKeys(fn ($y) => [$y => VatPeriods::periodsForYear($frequency, $y)]),
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

    public function pdf(VatReturnPeriod $vatReturn, MpdfRenderer $renderer)
    {
        $company = Auth::user()->company;

        $pdf = $renderer->render('user.reports.pdf.vat-return-period', [
            'company' => $company,
            'period' => $vatReturn,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="vat-return-'.$vatReturn->period_start->format('Y-m-d').'-to-'.$vatReturn->period_end->format('Y-m-d').'.pdf"',
        ]);
    }

    public function destroy(VatReturnPeriod $vatReturn)
    {
        $vatReturn->delete();

        return redirect()->route('app.reports.vat-returns.index')->with('status', __('VAT return period deleted.'));
    }

    /**
     * The day after the latest filed period's end, or today's period
     * start if nothing has been filed yet — a reasonable default so the
     * create form opens already pointed at "whatever comes next" instead
     * of an arbitrary blank range.
     */
    private function suggestedNextStart(int $companyId, string $frequency): Carbon
    {
        $latest = VatReturnPeriod::where('company_id', $companyId)->orderByDesc('period_end')->first();

        return $latest ? $latest->period_end->copy()->addDay() : VatPeriods::periodContaining($frequency, now())['start']->copy();
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

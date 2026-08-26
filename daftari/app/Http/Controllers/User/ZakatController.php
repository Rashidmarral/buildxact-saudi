<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ZakatCalculation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A Zakat ESTIMATE for internal planning — not a substitute for the actual
 * Zakat return filed through ZATCA's own portal. It uses the standard
 * "net invested capital" method (equity + long-term financing, minus net
 * fixed assets and other deductions) x 2.5%/2.5775%, the same simplified
 * formula the marketing site's standalone Zakat calculator already uses —
 * the difference here is that the equity figure is pulled from the
 * company's real posted ledger instead of typed in by hand.
 */
class ZakatController extends Controller
{
    public function index()
    {
        $calculations = ZakatCalculation::latest('period_end_date')->latest('id')->paginate(15);

        return view('user.zakat.index', compact('calculations'));
    }

    public function create(Request $request)
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();
        $equity = $this->totalEquityAsOf(Auth::user()->company_id, $asOf);

        return view('user.zakat.create', ['asOf' => $asOf, 'equity' => $equity]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_end_date' => ['required', 'date'],
            'rate_type' => ['required', Rule::in(['hijri', 'gregorian'])],
            'equity_amount' => ['required', 'numeric'],
            'long_term_liabilities' => ['nullable', 'numeric', 'min:0'],
            'net_fixed_assets' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $longTermLiabilities = (float) ($data['long_term_liabilities'] ?? 0);
        $netFixedAssets = (float) ($data['net_fixed_assets'] ?? 0);
        $otherDeductions = (float) ($data['other_deductions'] ?? 0);

        $zakatBase = max(0, $data['equity_amount'] + $longTermLiabilities - $netFixedAssets - $otherDeductions);
        $zakatDue = $zakatBase * ZakatCalculation::rate($data['rate_type']);

        $calculation = Auth::user()->company->zakatCalculations()->create([
            'created_by' => Auth::id(),
            'period_end_date' => $data['period_end_date'],
            'rate_type' => $data['rate_type'],
            'equity_amount' => $data['equity_amount'],
            'long_term_liabilities' => $longTermLiabilities,
            'net_fixed_assets' => $netFixedAssets,
            'other_deductions' => $otherDeductions,
            'zakat_base' => $zakatBase,
            'zakat_due' => $zakatDue,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::record('zakat.calculate', $calculation, __('Calculated Zakat estimate for period ending :date', ['date' => $calculation->period_end_date->format('Y-m-d')]));

        return redirect()->route('app.zakat.show', $calculation)->with('status', __('Zakat estimate saved.'));
    }

    public function show(ZakatCalculation $zakat)
    {
        return view('user.zakat.show', ['calculation' => $zakat]);
    }

    public function destroy(ZakatCalculation $zakat)
    {
        $zakat->delete();

        return redirect()->route('app.zakat.index')->with('status', __('Zakat estimate deleted.'));
    }

    public function downloadPdf(ZakatCalculation $zakat)
    {
        $pdf = Pdf::loadView('user.zakat.pdf', ['company' => Auth::user()->company, 'calculation' => $zakat]);

        return $pdf->download('zakat-estimate-'.$zakat->period_end_date->format('Y-m-d').'.pdf');
    }

    private function totalEquityAsOf(int $companyId, Carbon $asOf): float
    {
        $equity = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'equity')
            ->get()
            ->sum(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');

                return $credit - $debit;
            });

        $netIncomeToDate = Account::where('company_id', $companyId)
            ->whereIn('type', ['revenue', 'expense'])
            ->get()
            ->sum(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');

                return $account->type === 'revenue' ? $credit - $debit : -($debit - $credit);
            });

        return round($equity + $netIncomeToDate, 2);
    }
}

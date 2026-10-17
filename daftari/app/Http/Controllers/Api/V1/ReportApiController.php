<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Services\Reports\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only JSON versions of the three core GL reports (User\ReportController
 * has the web equivalents, with CSV/PDF export) — same figures, since both
 * go through FinancialReportService. period/from/to/as_of query params
 * match the web report pages' semantics exactly (see ResolvesReportPeriod).
 */
class ReportApiController extends Controller
{
    use ResolvesReportPeriod;

    public function trialBalance(Request $request, FinancialReportService $reports)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $rows = $reports->trialBalance($company, $period['from'], $period['to']);

        return response()->json([
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'rows' => $rows->map(fn ($r) => [
                'account_code' => $r['account']->code,
                'account_name' => $r['account']->name,
                'debit' => $r['debit'],
                'credit' => $r['credit'],
            ]),
            'total_debit' => $rows->sum('debit'),
            'total_credit' => $rows->sum('credit'),
        ]);
    }

    public function balanceSheet(Request $request, FinancialReportService $reports)
    {
        $company = Auth::user()->company;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of'))->endOfDay() : now()->endOfDay();
        $data = $reports->balanceSheet($company, $asOf);

        $section = fn ($rows) => $rows->map(fn ($r) => [
            'account_code' => $r['account']?->code ?? ($r['key'] ?? null),
            'account_name' => $r['account']?->name ?? ($r['label'] ?? null),
            'balance' => $r['balance'],
        ])->values();

        return response()->json([
            'as_of' => $asOf->toDateString(),
            'assets' => $section($data['assets']),
            'liabilities' => $section($data['liabilities']),
            'equity' => $section($data['equity']),
            'total_assets' => $data['totalAssets'],
            'total_liabilities' => $data['totalLiabilities'],
            'total_equity' => $data['totalEquity'],
            'balanced' => $data['balanced'],
        ]);
    }

    public function incomeStatement(Request $request, FinancialReportService $reports)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);
        $data = $reports->incomeStatement($company, $period['from'], $period['to']);

        $section = fn ($rows) => $rows->map(fn ($r) => [
            'account_code' => $r['account']->code,
            'account_name' => $r['account']->name,
            'amount' => $r['amount'],
        ])->values();

        return response()->json([
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'revenue' => $section($data['revenueLines']),
            'expenses' => $section($data['expenseLines']),
            'net_sales' => $data['netSales'],
            'gross_profit' => $data['grossProfit'],
            'operating_profit' => $data['operatingProfit'],
            'net_profit' => $data['netProfit'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Services\Audit\CompanyAuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CompanyAuditController extends Controller
{
    use ExportsCsv, ResolvesReportPeriod;

    public function index(Request $request, CompanyAuditService $audit)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $result = $audit->run($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('company-audit.csv', [__('Section'), __('Status'), __('Finding'), __('Summary')],
                collect($result['sections'])->flatMap(function (array $section) {
                    if ($section['items']->isEmpty()) {
                        return [[$section['label'], ucfirst($section['status']), '', $section['summary']]];
                    }

                    return $section['items']->map(fn (array $item) => [$section['label'], ucfirst($section['status']), $item['label'], $section['summary']]);
                }));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('user.audit.pdf', [
                'company' => $company,
                'period' => $period,
                'overallStatus' => $result['overall_status'],
                'sections' => $result['sections'],
                'transactions' => $result['transactions'],
                'transactionTotals' => $result['transaction_totals'],
                'locale' => App::getLocale(),
            ]);

            return $pdf->download('company-audit-'.$period['from']->format('Y-m-d').'-to-'.$period['to']->format('Y-m-d').'.pdf');
        }

        return view('user.audit.index', [
            'company' => $company,
            'period' => $period,
            'overallStatus' => $result['overall_status'],
            'sections' => $result['sections'],
            'transactions' => $result['transactions'],
            'transactionTotals' => $result['transaction_totals'],
        ]);
    }
}

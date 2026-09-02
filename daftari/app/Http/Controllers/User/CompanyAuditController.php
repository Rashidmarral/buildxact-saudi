<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Services\Audit\CompanyAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyAuditController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request, CompanyAuditService $audit)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $result = $audit->run($company, $period['from'], $period['to']);

        return view('user.audit.index', [
            'company' => $company,
            'period' => $period,
            'overallStatus' => $result['overall_status'],
            'sections' => $result['sections'],
        ]);
    }
}

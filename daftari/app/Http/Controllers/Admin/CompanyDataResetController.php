<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Admin\CompanyDataResetService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Super-admin "Company data reset" — see CompanyDataResetService's own
 * docblock for exactly what this does and does not touch. Reachable only
 * through the same destructive-admin-action gate as "Ledger cleanup" and
 * "Reset settings" (admin.permission:companies,manage + a fresh admin
 * password confirmation), plus its own typed-confirmation step here:
 * the admin must retype the company's exact name before execute() runs,
 * on top of selecting which categories to wipe — a single misclick on a
 * company list is not enough to trigger this.
 */
class CompanyDataResetController extends Controller
{
    public function show(Company $company, CompanyDataResetService $service)
    {
        return view('admin.companies.data-reset', [
            'company' => $company,
            'grouped' => $service->groupedRegistry(),
            'counts' => $service->preview($company),
            'hasZatcaActivity' => $company->hasAnyZatcaActivity(),
        ]);
    }

    public function execute(Request $request, Company $company, CompanyDataResetService $service)
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'in:'.implode(',', array_keys($service->registry()))],
            'confirm_name' => ['required', 'string'],
        ]);

        if ($data['confirm_name'] !== $company->name) {
            return back()->withErrors(['confirm_name' => __('Type the company name exactly as shown to confirm — this cannot be undone.')])->withInput();
        }

        try {
            $result = $service->execute($company, $data['categories']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['company' => $e->getMessage()]);
        }

        $totalDeleted = array_sum($result['deleted']);

        AuditLog::record(
            'company.data_reset',
            $company,
            __('Permanently wiped :count transaction record(s) for :name across :categories categories, removed :journal journal entries, and reset numbering for the wiped categories', [
                'count' => $totalDeleted,
                'name' => $company->name,
                'categories' => count($data['categories']),
                'journal' => $result['journal_entries_removed'],
            ]),
            old: null,
            new: $result
        );

        return redirect()->route('admin.companies.show', $company)->with('status', __(
            'Wiped :count transaction record(s) across :categories categories for :name. Document numbering for those categories now restarts at 1.',
            ['count' => $totalDeleted, 'categories' => count($data['categories']), 'name' => $company->name]
        ));
    }
}

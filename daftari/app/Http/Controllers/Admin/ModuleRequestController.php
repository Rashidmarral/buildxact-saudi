<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyOverride;
use App\Models\ModuleRequest;
use App\Support\PlatformFeatureToggle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The review queue behind the company-facing Modules marketplace (see
 * User\ModuleRequestController). Approving a request is just a friendly
 * front door onto the CompanyOverride mechanism the Companies screen's
 * feature-override list already exposes directly — this doesn't add a
 * second way for a module to become enabled, only a guided one.
 *
 * "on admin side approve the request status show module install login
 * back to dynamic in module it still says request to install module" —
 * approving only ever sets the per-company override; FeatureAccessService
 * checks the platform-wide master switch (Platform Settings > Features)
 * FIRST and treats it as an absolute veto, so a module switched off for
 * the whole platform stays invisible to a company no matter how many of
 * its requests get approved, with nothing on this screen (before this
 * fix) hinting why. Both index() and approve() now surface that switch's
 * current state so an admin isn't left guessing.
 */
class ModuleRequestController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $status = $request->query('status', 'requested');

        $requests = ModuleRequest::with(['company', 'requester', 'reviewer'])
            ->when(in_array($status, ModuleRequest::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($this->resolvePerPage($request, 25))
            ->withQueryString();

        $platformDisabledKeys = collect($requests->items())
            ->pluck('module_key')->unique()
            ->reject(fn ($key) => PlatformFeatureToggle::isEnabled($key))
            ->all();

        return view('admin.module-requests.index', compact('requests', 'status', 'platformDisabledKeys'));
    }

    public function approve(ModuleRequest $moduleRequest)
    {
        if ($moduleRequest->status !== 'requested') {
            return back()->withErrors(['module_request' => __('This request has already been reviewed.')]);
        }

        $override = CompanyOverride::updateOrCreate(
            ['company_id' => $moduleRequest->company_id, 'type' => 'feature', 'key' => $moduleRequest->module_key],
            ['value' => '1', 'is_unlimited' => false, 'reason' => __('Module purchase approved'), 'created_by' => Auth::id()]
        );

        $moduleRequest->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        AuditLog::record(
            'module_request.approve',
            $moduleRequest,
            __(':module installed for :company', ['module' => $moduleRequest->moduleLabel(), 'company' => $moduleRequest->company->name])
        );

        // The same override the Companies screen's own feature-override
        // list would have created — recorded there too for anyone who
        // only ever looks at that per-company list.
        AuditLog::record('company.override.set', $moduleRequest->company, __('Set :type override for :key on :name', [
            'type' => 'feature', 'key' => $moduleRequest->module_key, 'name' => $moduleRequest->company->name,
        ]), old: null, new: $override->only(['value', 'is_unlimited', 'reason']));

        if (! PlatformFeatureToggle::isEnabled($moduleRequest->module_key)) {
            return back()->with('status', __(
                ':company is now approved for :module, but :module is currently switched OFF for the entire platform (Platform Settings > Features) — :company still won\'t see it until you also turn that on.',
                ['company' => $moduleRequest->company->name, 'module' => $moduleRequest->moduleLabel()]
            ));
        }

        return back()->with('status', __('Module installed for :company.', ['company' => $moduleRequest->company->name]));
    }

    public function reject(Request $request, ModuleRequest $moduleRequest)
    {
        if ($moduleRequest->status !== 'requested') {
            return back()->withErrors(['module_request' => __('This request has already been reviewed.')]);
        }

        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $moduleRequest->update([
            'status' => 'rejected', 'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => Auth::id(), 'reviewed_at' => now(),
        ]);

        AuditLog::record('module_request.reject', $moduleRequest, __('Rejected :module for :company', [
            'module' => $moduleRequest->moduleLabel(), 'company' => $moduleRequest->company->name,
        ]));

        return back()->with('status', __('Request rejected.'));
    }
}

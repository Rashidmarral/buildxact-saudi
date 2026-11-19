<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ModuleRequest;
use App\Services\Features\FeatureAccessService;
use App\Support\FeatureRegistry;
use App\Support\ModulePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Modules" marketplace: every paid, admin-installed module (Payroll,
 * POS, Restaurant Management, ...) in one place, so a company can see
 * what's available, what it costs, and request to have it turned on —
 * rather than those modules just silently existing in the sidebar
 * whether or not the company has actually signed up for them.
 */
class ModuleRequestController extends Controller
{
    public function index(FeatureAccessService $access)
    {
        $company = Auth::user()->company;

        $latestByKey = $company->moduleRequests()->with('reviewer')->latest()->get()->groupBy('module_key');

        $modules = collect(FeatureRegistry::catalog())
            ->filter(fn ($entry) => $entry['type'] === 'gated')
            ->map(function ($entry, $key) use ($company, $access, $latestByKey) {
                $latest = $latestByKey->get($key)?->first();

                return [
                    'key' => $key,
                    'label' => $entry['label'],
                    'price' => ModulePricing::get($key),
                    'enabled' => $access->enabled($company, $key),
                    'latestRequest' => $latest,
                ];
            })
            ->values();

        return view('user.modules.index', compact('modules'));
    }

    public function store(Request $request, string $key, FeatureAccessService $access)
    {
        $entry = FeatureRegistry::catalog()[$key] ?? null;
        abort_unless($entry && $entry['type'] === 'gated', 404);

        $company = Auth::user()->company;

        if ($access->enabled($company, $key)) {
            return back()->withErrors(['module' => __('This module is already installed.')]);
        }

        if ($company->moduleRequests()->where('module_key', $key)->where('status', 'requested')->exists()) {
            return back()->withErrors(['module' => __('You already have a pending request for this module.')]);
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $moduleRequest = ModuleRequest::create([
            'company_id' => $company->id,
            'module_key' => $key,
            'status' => 'requested',
            'note' => $data['note'] ?? null,
            'requested_by' => Auth::id(),
        ]);

        AuditLog::record('module_request.create', $moduleRequest, __('Requested to install :module', ['module' => $moduleRequest->moduleLabel()]));

        return back()->with('status', __('Request sent — our team will review it shortly.'));
    }
}

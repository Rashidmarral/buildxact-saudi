<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\MachineryAsset;
use App\Models\MachineryMaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Service history + the next-due date that actually drives the
 * reminder (MachineryAsset.next_maintenance_due_date — see
 * SendMachineryExpiryReminders). Logging an event never changes the
 * asset's status; "maintenance" as a status is for the owner to set by
 * hand on the asset itself when a machine is genuinely out of service,
 * independent of whether a service event happens to be logged today.
 */
class MachineryMaintenanceLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $logs = MachineryMaintenanceLog::with('machinery', 'expense')
            ->orderByDesc('performed_at')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        $upcoming = MachineryAsset::whereNotNull('next_maintenance_due_date')
            ->orderBy('next_maintenance_due_date')
            ->get();

        return view('user.machinery.maintenance.index', compact('logs', 'upcoming'));
    }

    public function create(Request $request)
    {
        return view('user.machinery.maintenance.form', [
            'machinery' => MachineryAsset::orderBy('name')->get(),
            'selectedMachineryId' => $request->integer('machinery_asset_id') ?: null,
            'expenses' => Expense::where('machinery_asset_id', $request->integer('machinery_asset_id'))->orderByDesc('expense_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'machinery_asset_id' => ['required', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'service_type' => ['required', 'string', 'max:100'],
            'performed_at' => ['required', 'date'],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where('company_id', $companyId)],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:performed_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $machinery = MachineryAsset::findOrFail($data['machinery_asset_id']);

        $log = MachineryMaintenanceLog::create($data + [
            'company_id' => $machinery->company_id,
            'created_by' => Auth::id(),
        ]);

        $machinery->update([
            'next_maintenance_due_date' => $data['next_due_date'] ?? null,
            // A new due date supersedes any reminder already sent for the
            // old one — the same guard-reset SendMachineryExpiryReminders
            // itself applies when a date moves out of its window.
            'maintenance_reminder_sent_at' => null,
        ]);

        AuditLog::record('machinery.maintenance.create', $log, __(':type logged for :code', ['type' => $log->service_type, 'code' => $machinery->asset_code]));

        return redirect()->route('app.machinery.maintenance.index')->with('status', __('Maintenance logged.'));
    }
}

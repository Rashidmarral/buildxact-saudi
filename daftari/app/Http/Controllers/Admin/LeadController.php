<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin / permitted-staff CRM for prospective customers of this
 * SaaS itself (item 10 of the Sales, Compliance & Business Growth
 * request). Every stage/assignment change is audit-logged the same way
 * TicketController logs its own actions, so a lead's full history is
 * reconstructible from AuditLog + its notes.
 */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()->with(['assignedAdmin:id,name', 'convertedCompany:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }
        if ($request->filled('industry')) {
            $query->where('industry', $request->string('industry'));
        }
        if ($request->filled('assigned')) {
            $request->string('assigned') === 'unassigned'
                ? $query->whereNull('assigned_admin_id')
                : $query->where('assigned_admin_id', $request->integer('assigned'));
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%"));
        }

        $leads = $query->latest('id')->paginate(20)->withQueryString();

        $openStages = array_diff(Lead::STAGES, ['paid', 'active', 'renewal', 'lost']);
        $stats = [
            'open' => Lead::whereIn('status', $openStages)->count(),
            'new_this_month' => Lead::where('created_at', '>=', now()->startOfMonth())->count(),
            'demos_scheduled' => Lead::whereNotNull('demo_at')->where('demo_at', '>=', now())->count(),
            'won' => Lead::whereIn('status', Lead::WON_STAGES)->count(),
            'lost' => Lead::where('status', 'lost')->count(),
        ];
        $totalDecided = $stats['won'] + $stats['lost'];
        $stats['conversion_rate'] = $totalDecided > 0 ? round($stats['won'] / $totalDecided * 100) : null;

        $admins = $this->assignableAdmins();

        return view('admin.leads.index', compact('leads', 'stats', 'admins'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['assignedAdmin', 'convertedCompany', 'notes.author', 'setupPackageRequest.setupPackage']);

        $history = AuditLog::with('admin')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->latest('id')
            ->get();

        $timeline = $lead->notes
            ->map(fn ($note) => ['at' => $note->created_at, 'author' => $note->author?->name, 'body' => $note->body, 'is_note' => true])
            ->concat($history->map(fn ($entry) => ['at' => $entry->created_at, 'author' => $entry->admin?->name, 'body' => $entry->description, 'is_note' => false]))
            ->sortByDesc('at')
            ->values();

        $admins = $this->assignableAdmins();
        $companies = Company::orderBy('name')->limit(200)->get(['id', 'name']);

        return view('admin.leads.show', compact('lead', 'timeline', 'admins', 'companies'));
    }

    public function addNote(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $lead->notes()->create([
            'author_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        $lead->update(['last_activity_at' => now()]);

        return back()->with('status', __('Note added.'));
    }

    public function changeStatus(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Lead::STAGES)],
            'lost_reason' => ['nullable', 'string', 'max:255', 'required_if:status,lost'],
        ]);

        $old = $lead->status;
        $lead->update([
            'status' => $data['status'],
            'lost_reason' => $data['status'] === 'lost' ? $data['lost_reason'] : null,
            'last_activity_at' => now(),
        ]);

        AuditLog::record(
            'lead.status_change',
            $lead,
            __('Moved lead :name from :old to :new', ['name' => $lead->name, 'old' => $old, 'new' => $data['status']]),
            old: ['status' => $old],
            new: ['status' => $data['status']],
        );

        return back()->with('status', __('Stage updated.'));
    }

    public function assign(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'assigned_admin_id' => ['nullable', 'exists:users,id'],
        ]);

        $admin = $data['assigned_admin_id'] ? User::find($data['assigned_admin_id']) : null;
        abort_if($admin && ! ($admin->isSuperAdmin() || $admin->isAdminStaff()), 422, __('That user is not an admin.'));

        $old = ['assigned_admin_id' => $lead->assigned_admin_id];
        $lead->update(['assigned_admin_id' => $admin?->id, 'last_activity_at' => now()]);

        AuditLog::record(
            'lead.assign',
            $lead,
            $admin ? __('Assigned lead :name to :assignee', ['name' => $lead->name, 'assignee' => $admin->name]) : __('Unassigned lead :name', ['name' => $lead->name]),
            old: $old,
            new: ['assigned_admin_id' => $admin?->id],
        );

        return back()->with('status', __('Assignment updated.'));
    }

    public function scheduleFollowUp(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'demo_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $lead->update([
            'demo_at' => $data['demo_at'] ?? null,
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            'last_activity_at' => now(),
        ]);

        return back()->with('status', __('Follow-up saved.'));
    }

    public function linkCompany(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'converted_company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $lead->update(['converted_company_id' => $data['converted_company_id'] ?? null, 'last_activity_at' => now()]);

        AuditLog::record(
            'lead.link_company',
            $lead,
            __('Linked lead :name to a company account', ['name' => $lead->name]),
            new: ['converted_company_id' => $data['converted_company_id'] ?? null],
        );

        return back()->with('status', __('Company link updated.'));
    }

    public function updateSetupPackageStatus(Request $request, Lead $lead)
    {
        $setupPackageRequest = $lead->setupPackageRequest()->firstOrFail();

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', \App\Models\SetupPackageRequest::STATUSES)],
        ]);

        $setupPackageRequest->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : $setupPackageRequest->completed_at,
        ]);

        return back()->with('status', __('Setup package request updated.'));
    }

    /**
     * Mirrors TicketController::assignableAdmins() — super_admin always
     * qualifies; admin_staff only if their AdminRole grants 'leads'.
     */
    private function assignableAdmins()
    {
        return User::whereIn('role', ['super_admin', 'admin_staff'])
            ->with('adminRoles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasAdminPermission('leads'))
            ->values();
    }
}

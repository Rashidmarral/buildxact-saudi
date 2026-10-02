<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin support ticket management (Module 18). Every mutating action
 * here is audit-logged (see AuditLog::record calls below); internal notes
 * are readable here (unlike the company-side TicketController) but the
 * reverse leak — an internal note reaching the company — is prevented at
 * the company-side controller, not here, since that's the actual boundary
 * that matters.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::query()->with(['company:id,name', 'assignedAdmin:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('assigned')) {
            $request->string('assigned') === 'unassigned'
                ? $query->whereNull('assigned_admin_id')
                : $query->where('assigned_admin_id', $request->integer('assigned'));
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('subject', 'like', "%{$term}%")->orWhere('ticket_number', 'like', "%{$term}%"));
        }

        $tickets = $query->latest('id')->paginate(20)->withQueryString();

        $stats = [
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'waiting_customer' => Ticket::where('status', 'waiting_customer')->count(),
            'urgent' => Ticket::whereIn('status', ['open', 'in_progress', 'waiting_customer'])->where('priority', 'urgent')->count(),
            'unassigned' => Ticket::whereIn('status', ['open', 'in_progress', 'waiting_customer'])->whereNull('assigned_admin_id')->count(),
        ];

        $companies = Company::orderBy('name')->get(['id', 'name']);
        $admins = $this->assignableAdmins();

        return view('admin.tickets.index', compact('tickets', 'stats', 'companies', 'admins'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['company', 'user', 'assignedAdmin', 'replies' => fn ($q) => $q->with(['author', 'attachments'])->oldest('id')]);
        $initialAttachments = $ticket->attachments()->whereNull('ticket_reply_id')->get();

        $companyHistory = Ticket::where('company_id', $ticket->company_id)
            ->where('id', '!=', $ticket->id)
            ->latest('id')
            ->take(10)
            ->get();

        $admins = $this->assignableAdmins();

        return view('admin.tickets.show', compact('ticket', 'initialAttachments', 'companyHistory', 'admins'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => TicketAttachment::validationRules(),
        ]);

        $reply = $ticket->replies()->create([
            'author_id' => Auth::id(),
            'body' => $data['body'],
            'is_internal_note' => false,
        ]);

        if ($request->hasFile('attachment')) {
            TicketAttachment::storeUpload($ticket, $reply, $request->file('attachment'), Auth::id(), false);
        }

        $ticket->update(['last_reply_at' => now()]);

        AuditLog::record('ticket.reply', $ticket, __('Admin replied on ticket :number', ['number' => $ticket->ticket_number]), companyId: $ticket->company_id);

        $ticket->user?->notify(new GenericNotification(
            title: __('New reply on your ticket'),
            body: __(':number: :subject', ['number' => $ticket->ticket_number, 'subject' => $ticket->subject]),
            url: route('app.tickets.show', $ticket),
            icon: 'support',
        ));

        return back()->with('status', __('Reply sent.'));
    }

    /**
     * Internal notes are never notified to the customer, and the audit
     * description here deliberately carries no note content — only the
     * fact that one was added — since AuditLog::forCompany() means this
     * same record is visible on the company's own Activity Log page too.
     */
    public function addNote(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => TicketAttachment::validationRules(),
        ]);

        $note = $ticket->replies()->create([
            'author_id' => Auth::id(),
            'body' => $data['body'],
            'is_internal_note' => true,
        ]);

        if ($request->hasFile('attachment')) {
            TicketAttachment::storeUpload($ticket, $note, $request->file('attachment'), Auth::id(), true);
        }

        AuditLog::record('ticket.internal_note', $ticket, __('Added an internal note on ticket :number', ['number' => $ticket->ticket_number]), companyId: $ticket->company_id);

        return back()->with('status', __('Internal note added.'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'assigned_admin_id' => ['nullable', 'exists:users,id'],
        ]);

        $admin = $data['assigned_admin_id'] ? User::find($data['assigned_admin_id']) : null;
        abort_if($admin && ! ($admin->isSuperAdmin() || $admin->isAdminStaff()), 422, __('That user is not an admin.'));

        $old = ['assigned_admin_id' => $ticket->assigned_admin_id];
        $ticket->update(['assigned_admin_id' => $admin?->id]);

        AuditLog::record(
            'ticket.assign',
            $ticket,
            $admin ? __('Assigned ticket :number to :name', ['number' => $ticket->ticket_number, 'name' => $admin->name]) : __('Unassigned ticket :number', ['number' => $ticket->ticket_number]),
            old: $old,
            new: ['assigned_admin_id' => $admin?->id],
            companyId: $ticket->company_id,
        );

        return back()->with('status', __('Ticket assignment updated.'));
    }

    public function changePriority(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'priority' => ['required', 'in:'.implode(',', Ticket::PRIORITIES)],
        ]);

        $old = $ticket->priority;
        $ticket->update(['priority' => $data['priority']]);

        AuditLog::record(
            'ticket.priority_change',
            $ticket,
            __('Changed priority of ticket :number from :old to :new', ['number' => $ticket->ticket_number, 'old' => $old, 'new' => $data['priority']]),
            old: ['priority' => $old],
            new: ['priority' => $data['priority']],
            companyId: $ticket->company_id,
        );

        return back()->with('status', __('Priority updated.'));
    }

    public function changeStatus(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Ticket::STATUSES)],
        ]);

        $old = $ticket->status;
        $updates = ['status' => $data['status']];

        if ($data['status'] === 'resolved' && $old !== 'resolved') {
            $updates['resolved_at'] = now();
        }
        if ($data['status'] === 'closed' && $old !== 'closed') {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        AuditLog::record(
            'ticket.status_change',
            $ticket,
            __('Changed status of ticket :number from :old to :new', ['number' => $ticket->ticket_number, 'old' => $old, 'new' => $data['status']]),
            old: ['status' => $old],
            new: ['status' => $data['status']],
            companyId: $ticket->company_id,
        );

        $ticket->user?->notify(new GenericNotification(
            title: __('Your ticket status changed'),
            body: __(':number is now :status', ['number' => $ticket->ticket_number, 'status' => $ticket->statusLabel()]),
            url: route('app.tickets.show', $ticket),
            icon: 'support',
        ));

        return back()->with('status', __('Status updated.'));
    }

    /**
     * Unlike the company-side download, an admin may fetch ANY attachment
     * on ANY ticket — including internal-note attachments — since viewing
     * across the whole platform is the entire point of this controller.
     */
    public function downloadAttachment(TicketAttachment $attachment)
    {
        return $attachment->downloadResponse();
    }

    /**
     * super_admin always qualifies; admin_staff only if their AdminRole
     * grants the 'tickets' permission — matches EnsureAdminPermission's
     * own check (User::hasAdminPermission()), just applied here to build
     * the assignment dropdown instead of gating a route.
     */
    private function assignableAdmins()
    {
        return User::whereIn('role', ['super_admin', 'admin_staff'])
            ->with('adminRoles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasAdminPermission('tickets'))
            ->values();
    }
}

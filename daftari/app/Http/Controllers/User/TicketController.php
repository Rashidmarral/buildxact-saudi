<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Company-side support tickets (Module 18). A company user only ever sees
 * their own company's tickets (Ticket uses BelongsToCompany, so this is
 * enforced by the query itself, not just by convention) and only ever
 * sees public replies — never an internal note; see
 * Ticket::publicReplies() and the deliberate omission of
 * Ticket::internalNotes()/replies() anywhere in this controller.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::where('company_id', Auth::user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $tickets = $query->latest('id')->paginate(20)->withQueryString();

        return view('user.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('user.tickets.create', ['ticket' => new Ticket]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:'.implode(',', Ticket::PRIORITIES)],
            'attachment' => TicketAttachment::validationRules(),
        ]);

        $ticket = Ticket::create([
            'company_id' => Auth::user()->company_id,
            'user_id' => Auth::id(),
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => 'open',
        ]);

        if ($request->hasFile('attachment')) {
            TicketAttachment::storeUpload($ticket, null, $request->file('attachment'), Auth::id(), false);
        }

        AuditLog::record('ticket.create', $ticket, __('Opened ticket :number: :subject', ['number' => $ticket->ticket_number, 'subject' => $ticket->subject]));

        return redirect()->route('app.tickets.show', $ticket)->with('status', __('Support ticket opened. Our team will respond soon.'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['assignedAdmin', 'publicReplies' => fn ($q) => $q->with(['author', 'attachments' => fn ($a) => $a->where('is_internal', false)])->oldest('id')]);
        $initialAttachments = $ticket->attachments()->whereNull('ticket_reply_id')->where('is_internal', false)->get();

        return view('user.tickets.show', compact('ticket', 'initialAttachments'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->isOpenForReply(), 422);

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

        // A customer reply on a ticket the company was waiting to hear
        // back on naturally puts the ball back in the support team's
        // court — matches the Waiting-for-customer status's own meaning.
        $updates = ['last_reply_at' => now()];
        if ($ticket->status === 'waiting_customer') {
            $updates['status'] = 'open';
        }
        $ticket->update($updates);

        AuditLog::record('ticket.reply', $ticket, __('Replied on ticket :number', ['number' => $ticket->ticket_number]));

        return back()->with('status', __('Reply sent.'));
    }

    /**
     * Tenant isolation here is two layers deep: TicketAttachment's
     * BelongsToCompany scope means route-model binding itself 404s for an
     * attachment belonging to another company, and is_internal is checked
     * explicitly since an internal-note attachment's company_id DOES
     * legitimately match this company — it must still never be served.
     */
    public function downloadAttachment(TicketAttachment $attachment)
    {
        abort_if($attachment->is_internal, 404);

        return $attachment->downloadResponse();
    }
}

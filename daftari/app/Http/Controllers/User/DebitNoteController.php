<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SyncDebitNoteToZatca;
use App\Models\AuditLog;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\Invoice;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sales-side Debit Note — the mirror of Credit Note: raises what a
 * customer owes on top of an already-issued invoice (an under-billed
 * charge, a price correction) instead of reducing it. A real
 * ZATCA-regulated document (InvoiceTypeCode 383), unlike Purchase Return
 * which is a buyer-side adjustment ZATCA never regulates.
 */
class DebitNoteController extends Controller
{
    private const ELIGIBLE_STATUSES = ['sent', 'partially_paid', 'paid', 'overdue'];

    public function index()
    {
        $debitNotes = DebitNote::with('client', 'invoice')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.debit-notes.index', compact('debitNotes'));
    }

    public function eligibleInvoices(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $invoices = Invoice::with('client')
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('issue_date')
            ->take(20)
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client->name,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'total' => number_format($invoice->total, 2),
            ])
            ->values();

        return response()->json($invoices);
    }

    public function create(Request $request)
    {
        $invoiceId = $request->query('invoice_id');

        if (! $invoiceId) {
            return view('user.debit-notes.select-invoice');
        }

        $invoice = Invoice::findOrFail($invoiceId);

        if (! in_array($invoice->status, self::ELIGIBLE_STATUSES, true)) {
            return redirect()->route('app.debit-notes.create')->withErrors(['invoice' => __('This invoice is no longer eligible for a debit note.')]);
        }

        $company = Auth::user()->company;

        return view('user.debit-notes.form', [
            'invoice' => $invoice,
            'nextNumberPreview' => $company->debit_note_prefix.'-'.str_pad((string) $company->next_debit_note_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')],
            'issue_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        if (! in_array($invoice->status, self::ELIGIBLE_STATUSES, true)) {
            return back()->withErrors(['invoice' => __('This invoice is no longer eligible for a debit note.')])->withInput();
        }

        $debitNote = DB::transaction(function () use ($data, $invoice) {
            $company = Auth::user()->company;

            $debitNote = DebitNote::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'branch_id' => $invoice->branch_id,
                'created_by' => Auth::id(),
                'debit_note_number' => $company->nextDebitNoteNumber(),
                'issue_date' => $data['issue_date'],
                'reason' => $data['reason'] ?? null,
                'status' => 'issued',
                'currency' => $invoice->currency,
            ]);

            foreach ($data['items'] as $row) {
                $item = new DebitNoteItem([
                    'debit_note_id' => $debitNote->id,
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => $row['vat_rate'],
                ]);
                $item->recalculate();
                $item->save();
            }

            $debitNote->recalculateTotals();

            return $debitNote;
        });

        app(LedgerPostingService::class)->postDebitNote($debitNote);

        AuditLog::record('debit_note.create', $debitNote, __('Issued debit note :number', ['number' => $debitNote->debit_note_number]));

        if ($debitNote->company->zatca_sync_frequency === 'instant') {
            SyncDebitNoteToZatca::dispatch($debitNote->id);
        }

        return redirect()->route('app.debit-notes.show', $debitNote)->with('status', __('Debit note issued.'));
    }

    public function show(DebitNote $debitNote)
    {
        $debitNote->load('items', 'client', 'invoice');
        $template = $debitNote->company->defaultTemplateFor('invoice');

        return view('user.debit-notes.show', compact('debitNote', 'template'));
    }

    public function downloadPdf(DebitNote $debitNote, MpdfRenderer $renderer)
    {
        $debitNote->load('items', 'client', 'invoice');

        $doc = [
            'type_label' => __('Debit Note'),
            'type_label_ar' => 'إشعار مدين',
            'number' => $debitNote->debit_note_number,
            'date_label' => __('Issued'),
            'date' => $debitNote->issue_date,
            'ref_no' => $debitNote->invoice->invoice_number,
            'party_label' => __('Debit to'),
            'party_label_ar' => 'العميل',
            'party' => $debitNote->client,
            'qr_code' => $debitNote->qr_code,
            'zatca_status' => $debitNote->zatcaDebitNoteLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
            'lines' => $debitNote->items,
            'subtotal' => $debitNote->subtotal,
            'vat_total' => $debitNote->vat_total,
            'total' => $debitNote->total,
            'notes' => $debitNote->reason,
        ];

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $doc,
            'company' => $debitNote->company,
            'template' => $debitNote->company->defaultTemplateFor('invoice'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$debitNote->debit_note_number.'.pdf"',
        ]);
    }

    public function downloadXml(DebitNote $debitNote)
    {
        $log = $debitNote->zatcaDebitNoteLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->first();

        if (! $log || ! $log->xml_payload) {
            return back()->withErrors(['debit_note' => __('This debit note has not been cleared or reported to ZATCA yet — there is no signed XML to download.')]);
        }

        return response($log->xml_payload, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$debitNote->debit_note_number.'.xml"',
        ]);
    }

    public function void(DebitNote $debitNote, LedgerPostingService $ledger)
    {
        if ($debitNote->status === 'void') {
            return back();
        }

        if ($debitNote->isZatcaSynced()) {
            return back()->withErrors(['debit_note' => __('This debit note has been cleared/reported to ZATCA and is now part of an immutable tax record — it can no longer be voided.')]);
        }

        DB::transaction(function () use ($debitNote, $ledger) {
            $ledger->reverse($debitNote->company, 'debit_note', $debitNote->id, __('Debit note :number voided', ['number' => $debitNote->debit_note_number]));
            $debitNote->update(['status' => 'void']);
        });

        return back()->with('status', __('Debit note voided.'));
    }
}

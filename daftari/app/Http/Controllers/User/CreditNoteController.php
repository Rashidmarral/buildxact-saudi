<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreditNoteController extends Controller
{
    private const ELIGIBLE_STATUSES = ['sent', 'partially_paid', 'paid', 'overdue'];

    public function index()
    {
        $creditNotes = CreditNote::with('client', 'invoice')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.credit-notes.index', compact('creditNotes'));
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
            ->filter(fn ($invoice) => $invoice->remainingCreditableTotal() > 0.01)
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client->name,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'total' => number_format($invoice->total, 2),
                'creditable' => number_format($invoice->remainingCreditableTotal(), 2),
            ])
            ->values();

        return response()->json($invoices);
    }

    public function create(Request $request)
    {
        $invoiceId = $request->query('invoice_id');

        if (! $invoiceId) {
            return view('user.credit-notes.select-invoice');
        }

        $invoice = Invoice::with('items.item')->findOrFail($invoiceId);

        if (! in_array($invoice->status, self::ELIGIBLE_STATUSES, true) || $invoice->remainingCreditableTotal() <= 0.01) {
            return redirect()->route('app.credit-notes.create')->withErrors(['invoice' => __('This invoice is no longer eligible for a credit note.')]);
        }

        $company = Auth::user()->company;

        return view('user.credit-notes.form', [
            'invoice' => $invoice,
            'nextNumberPreview' => $company->credit_note_prefix.'-'.str_pad((string) $company->next_credit_note_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')],
            'issue_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', Rule::exists('invoice_items', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['items'] = array_values(array_filter($data['items'], fn ($row) => (float) $row['quantity'] > 0));

        if (empty($data['items'])) {
            return back()->withErrors(['items' => __('Credit at least one line item.')])->withInput();
        }

        $invoice = Invoice::findOrFail($data['invoice_id']);

        $requestedTotal = array_sum(array_map(function ($row) {
            $lineSubtotal = (float) $row['quantity'] * (float) $row['unit_price'];

            return $lineSubtotal + round($lineSubtotal * ((float) $row['vat_rate'] / 100), 2);
        }, $data['items']));

        if ($requestedTotal - $invoice->remainingCreditableTotal() > 0.01) {
            return back()->withErrors(['items' => __('This credit note exceeds the amount remaining on the invoice.')])->withInput();
        }

        $creditNote = DB::transaction(function () use ($data, $invoice) {
            $company = Auth::user()->company;

            $creditNote = CreditNote::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'branch_id' => $invoice->branch_id,
                'created_by' => Auth::id(),
                'credit_note_number' => $company->nextCreditNoteNumber(),
                'issue_date' => $data['issue_date'],
                'reason' => $data['reason'] ?? null,
                'status' => 'issued',
                'currency' => $invoice->currency,
            ]);

            foreach ($data['items'] as $row) {
                $item = new CreditNoteItem([
                    'credit_note_id' => $creditNote->id,
                    'invoice_item_id' => $row['invoice_item_id'] ?? null,
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => $row['vat_rate'],
                ]);
                $item->recalculate();
                $item->save();
            }

            $creditNote->recalculateTotals();

            return $creditNote;
        });

        app(LedgerPostingService::class)->postCreditNote($creditNote);

        return redirect()->route('app.credit-notes.show', $creditNote)->with('status', __('Credit note issued.'));
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load('items', 'client', 'invoice');

        return view('user.credit-notes.show', compact('creditNote'));
    }

    public function void(CreditNote $creditNote, LedgerPostingService $ledger)
    {
        if ($creditNote->status === 'void') {
            return back();
        }

        $ledger->reverse($creditNote->company, 'credit_note', $creditNote->id, __('Credit note :number voided', ['number' => $creditNote->credit_note_number]));
        $creditNote->update(['status' => 'void']);

        return back()->with('status', __('Credit note voided.'));
    }
}

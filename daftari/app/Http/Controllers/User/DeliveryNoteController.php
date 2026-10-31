<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Unit;
use App\Services\DeliveryNoteService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * "if some companies order receive spare parts order and they have
 * deliver it to client and companies can issue delivery note for the
 * customers as well" — proof of delivery for goods shipped to a client,
 * either standalone or generated from an existing invoice (supporting
 * partial shipments — see DeliveryNoteService).
 */
class DeliveryNoteController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $deliveryNotes = DeliveryNote::with('client', 'invoice')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.delivery-notes.index', compact('deliveryNotes'));
    }

    /**
     * Blank standalone form by default; pass ?invoice_id= to pre-fill
     * from an eligible invoice's remaining-to-deliver lines instead.
     */
    public function create(Request $request)
    {
        if ($request->filled('invoice_id')) {
            $invoice = Invoice::with('items.item', 'items.unit', 'client')->findOrFail($request->query('invoice_id'));

            if ($invoice->status === 'draft' || $invoice->status === 'cancelled' || ! $invoice->hasUndeliveredItems()) {
                return redirect()->route('app.delivery-notes.select-invoice')
                    ->withErrors(['invoice' => __('This invoice is no longer eligible for a delivery note.')]);
            }

            $company = Auth::user()->company;

            return view('user.delivery-notes.create-from-invoice', [
                'invoice' => $invoice,
                'nextNumberPreview' => $company->delivery_note_prefix.'-'.str_pad((string) $company->next_delivery_note_number, 5, '0', STR_PAD_LEFT),
            ]);
        }

        $company = Auth::user()->company;

        return view('user.delivery-notes.create', [
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::orderBy('name')->get(['id', 'name', 'name_ar', 'sku', 'unit', 'base_unit_id']),
            'units' => Unit::orderBy('name')->get(),
            'nextNumberPreview' => $company->delivery_note_prefix.'-'.str_pad((string) $company->next_delivery_note_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function selectInvoice()
    {
        return view('user.delivery-notes.select-invoice');
    }

    public function eligibleInvoices(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $invoices = Invoice::with('client', 'items')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('issue_date')
            ->take(30)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->hasUndeliveredItems())
            ->take(20)
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client->name,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
            ])
            ->values();

        return response()->json($invoices);
    }

    public function store(Request $request, DeliveryNoteService $service)
    {
        $companyId = Auth::user()->company_id;

        if ($request->filled('invoice_id')) {
            $data = $request->validate([
                'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
                'issue_date' => ['required', 'date'],
                'driver_name' => ['nullable', 'string', 'max:255'],
                'vehicle_number' => ['nullable', 'string', 'max:100'],
                'received_by_name' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.invoice_item_id' => ['required', Rule::exists('invoice_items', 'id')],
                'items.*.quantity' => ['required', 'numeric', 'min:0'],
            ]);

            $invoice = Invoice::findOrFail($data['invoice_id']);

            try {
                $note = $service->createFromInvoice($invoice, $data, $data['items']);
            } catch (RuntimeException $e) {
                return back()->withErrors(['items' => $e->getMessage()])->withInput();
            }
        } else {
            $data = $request->validate([
                'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
                'issue_date' => ['required', 'date'],
                'driver_name' => ['nullable', 'string', 'max:255'],
                'vehicle_number' => ['nullable', 'string', 'max:100'],
                'received_by_name' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
                'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
                'items.*.description' => ['required', 'string', 'max:255'],
                'items.*.quantity' => ['required', 'numeric', 'min:0'],
            ]);

            try {
                $note = $service->createStandalone(Auth::user()->company, $data, $data['items']);
            } catch (RuntimeException $e) {
                return back()->withErrors(['items' => $e->getMessage()])->withInput();
            }
        }

        AuditLog::record('delivery_note.create', $note, __('Issued delivery note :number for :name', [
            'number' => $note->delivery_note_number, 'name' => $note->client->name,
        ]));

        return redirect()->route('app.delivery-notes.show', $note)->with('status', __('Delivery note issued.'));
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('items.item', 'items.unit', 'client', 'invoice', 'creator');
        $template = $deliveryNote->company->defaultTemplateFor('invoice');

        return view('user.delivery-notes.show', compact('deliveryNote', 'template'));
    }

    public function downloadPdf(DeliveryNote $deliveryNote, MpdfRenderer $renderer)
    {
        $deliveryNote->load('items.item', 'items.unit', 'client', 'invoice', 'creator');

        $pdf = $renderer->render('documents.print.delivery-note-pdf', [
            'company' => $deliveryNote->company,
            'deliveryNote' => $deliveryNote,
            'template' => $deliveryNote->company->defaultTemplateFor('invoice'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$deliveryNote->delivery_note_number.'.pdf"',
        ]);
    }

    public function cancel(Request $request, DeliveryNote $deliveryNote, DeliveryNoteService $service)
    {
        $data = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:500']]);

        try {
            $service->cancel($deliveryNote, $data['cancellation_reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['delivery_note' => $e->getMessage()]);
        }

        AuditLog::record('delivery_note.cancel', $deliveryNote, __('Cancelled delivery note :number', ['number' => $deliveryNote->delivery_note_number]));

        return back()->with('status', __('Delivery note cancelled.'));
    }
}

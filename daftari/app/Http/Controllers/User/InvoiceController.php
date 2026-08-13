<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('client')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('user.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();
        $company = Auth::user()->company;

        return view('user.invoices.form', [
            'invoice' => new Invoice(['issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()]),
            'clients' => $clients,
            'items' => $items,
            'nextNumberPreview' => $company->invoice_prefix.'-'.str_pad((string) $company->next_invoice_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $invoice = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'branch_id' => $company->default_branch_id,
                'created_by' => Auth::id(),
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => $data['type'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'currency' => $company->currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items']);
            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Invoice created.'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items', 'client', 'invoicePayments');

        return view('user.invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('items');
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();

        return view('user.invoices.form', compact('invoice', 'clients', 'items'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'client_id' => $data['client_id'],
                'type' => $data['type'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $data['items']);
            $invoice->recalculateTotals();
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Invoice updated.'));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->route('app.invoices.index')->with('status', __('Invoice deleted.'));
    }

    public function send(Invoice $invoice)
    {
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        return back()->with('status', __('Invoice marked as sent.'));
    }

    public function storePayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($invoice->balanceDue(), 0.01)],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice->invoicePayments()->create($data);
        $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
        $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
        $invoice->save();

        return back()->with('status', __('Payment recorded.'));
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'item_id' => $row['item_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? 15,
                'sort_order' => $sort,
            ]);
            $line->recalculate();
            $line->save();
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:standard,simplified'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}

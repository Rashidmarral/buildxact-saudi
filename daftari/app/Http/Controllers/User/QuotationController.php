<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Salesperson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $quotations = Quotation::with('client')
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all' => Quotation::count(),
            'draft' => Quotation::where('status', 'draft')->count(),
            'issued' => Quotation::where('status', 'issued')->count(),
            'accepted' => Quotation::where('status', 'accepted')->count(),
            'converted' => Quotation::where('status', 'converted')->count(),
            'expired' => Quotation::where('status', 'expired')->count(),
            'rejected' => Quotation::where('status', 'rejected')->count(),
        ];

        return view('user.quotations.index', compact('quotations', 'counts'));
    }

    public function create(Request $request)
    {
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();
        $salespersons = Salesperson::where('is_active', true)->orderBy('name')->get();
        $company = Auth::user()->company;
        $type = $request->get('type', 'quotation') === 'proforma' ? 'proforma' : 'quotation';

        return view('user.quotations.form', [
            'quotation' => new Quotation([
                'type' => $type,
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addDays(30)->toDateString(),
            ]),
            'clients' => $clients,
            'items' => $items,
            'salespersons' => $salespersons,
            'nextNumberPreview' => $type === 'proforma'
                ? $company->proforma_prefix.'-'.str_pad((string) $company->next_proforma_number, 5, '0', STR_PAD_LEFT)
                : $company->quotation_prefix.'-'.str_pad((string) $company->next_quotation_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $quotation = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $quotation = Quotation::create([
                'client_id' => $data['client_id'],
                'branch_id' => $company->default_branch_id,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'created_by' => Auth::id(),
                'quotation_number' => $company->nextQuotationNumber($data['type']),
                'type' => $data['type'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'currency' => $company->currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();

            return $quotation;
        });

        return redirect()->route('app.quotations.show', $quotation)->with('status', __('Quotation created.'));
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('items', 'client', 'convertedInvoice');
        $template = $quotation->company->defaultTemplateFor($quotation->type);

        return view('user.quotations.show', compact('quotation', 'template'));
    }

    public function edit(Quotation $quotation)
    {
        $quotation->load('items');
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();
        $salespersons = Salesperson::where('is_active', true)->orderBy('name')->get();

        return view('user.quotations.form', compact('quotation', 'clients', 'items', 'salespersons'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update([
                'client_id' => $data['client_id'],
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
        });

        return redirect()->route('app.quotations.show', $quotation)->with('status', __('Quotation updated.'));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return redirect()->route('app.quotations.index')->with('status', __('Quotation deleted.'));
    }

    public function send(Quotation $quotation)
    {
        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'issued']);
        }

        return back()->with('status', __('Quotation marked as issued.'));
    }

    public function accept(Quotation $quotation)
    {
        if (in_array($quotation->status, ['issued', 'draft'])) {
            $quotation->update(['status' => 'accepted']);
        }

        return back()->with('status', __('Quotation marked as accepted.'));
    }

    public function reject(Quotation $quotation)
    {
        if (! in_array($quotation->status, ['converted'])) {
            $quotation->update(['status' => 'rejected']);
        }

        return back()->with('status', __('Quotation marked as rejected.'));
    }

    public function convertToInvoice(Quotation $quotation)
    {
        if ($quotation->status === 'converted') {
            return back()->withErrors(['quotation' => __('This quotation has already been converted.')]);
        }

        $invoice = DB::transaction(function () use ($quotation) {
            $company = Auth::user()->company;

            $invoice = Invoice::create([
                'client_id' => $quotation->client_id,
                'branch_id' => $quotation->branch_id ?? $company->default_branch_id,
                'created_by' => Auth::id(),
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => 'standard',
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'discount_total' => $quotation->discount_total,
                'currency' => $quotation->currency,
                'notes' => $quotation->notes,
            ]);

            foreach ($quotation->items as $sort => $qItem) {
                $invoice->items()->create([
                    'item_id' => $qItem->item_id,
                    'description' => $qItem->description,
                    'quantity' => $qItem->quantity,
                    'unit_price' => $qItem->unit_price,
                    'vat_rate' => $qItem->vat_rate,
                    'vat_amount' => $qItem->vat_amount,
                    'line_total' => $qItem->line_total,
                    'sort_order' => $sort,
                ]);
            }

            $invoice->recalculateTotals();

            $quotation->update(['status' => 'converted', 'converted_invoice_id' => $invoice->id]);

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Quotation converted to invoice.'));
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new QuotationItem([
                'quotation_id' => $quotation->id,
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
            'salesperson_id' => ['nullable', Rule::exists('salespersons', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:quotation,proforma'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
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

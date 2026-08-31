<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $query = Bill::with('supplier')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'bills.csv',
                [__('Number'), __('Supplier'), __('Date'), __('Total'), __('Balance'), __('Status')],
                $query->get()->map(fn ($bill) => [
                    $bill->bill_number,
                    $bill->supplier->name,
                    $bill->bill_date->format('Y-m-d'),
                    number_format($bill->total, 2),
                    number_format($bill->balanceDue(), 2),
                    $bill->status,
                ])
            );
        }

        $bills = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => Bill::count(),
            'draft' => Bill::where('status', 'draft')->count(),
            'posted' => Bill::where('status', 'posted')->count(),
            'void' => Bill::where('status', 'void')->count(),
        ];

        return view('user.bills.index', compact('bills', 'counts'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.bills.form', [
            'bill' => new Bill(['bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'currency' => $company->currency, 'exchange_rate' => 1]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'currencies' => Currencies::catalog(),
            'nextNumberPreview' => $company->bill_prefix.'-'.str_pad((string) $company->next_bill_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $postImmediately = $request->boolean('post_immediately');

        $bill = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $bill = Bill::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $company->default_branch_id,
                'created_by' => Auth::id(),
                'bill_number' => $company->nextBillNumber(),
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'status' => 'draft',
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'currency' => $data['currency'] ?? $company->currency,
                'exchange_rate' => ($data['currency'] ?? $company->currency) === $company->currency ? 1 : ($data['exchange_rate'] ?? 1),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($bill, $data['items']);
            $bill->recalculateTotals();

            return $bill;
        });

        AuditLog::record('bill.create', $bill, __('Created bill :number', ['number' => $bill->bill_number]));

        if ($postImmediately) {
            $bill->update(['status' => 'posted']);
            $ledger->postBillPosted($bill);
            AuditLog::record('bill.post', $bill, __('Posted bill :number', ['number' => $bill->bill_number]));
        }

        return redirect()->route('app.bills.show', $bill)->with('status', $postImmediately ? __('Bill created and posted.') : __('Bill created.'));
    }

    public function show(Bill $bill)
    {
        $bill->load('items', 'supplier', 'billPayments', 'attachments');

        $template = $bill->company->defaultTemplateFor('bill');

        return view('user.bills.show', compact('bill', 'template'));
    }

    public function downloadPdf(Bill $bill, MpdfRenderer $renderer)
    {
        $bill->loadMissing('items', 'supplier');

        $doc = [
            'type_label' => __('Bill'),
            'type_label_ar' => 'فاتورة مشتريات',
            'number' => $bill->bill_number,
            'date_label' => __('Bill date'),
            'date' => $bill->bill_date,
            'date2_label' => __('Due date'),
            'date2_label_ar' => 'الاستحقاق',
            'date2' => $bill->due_date,
            'ref_no' => $bill->supplier_reference,
            'party_label' => __('Supplier'),
            'party_label_ar' => 'المورد',
            'party' => $bill->supplier,
            'lines' => $bill->items,
            'currency' => $bill->currency,
            'subtotal' => $bill->subtotal,
            'discount_total' => $bill->discount_total,
            'vat_total' => $bill->vat_total,
            'total' => $bill->total,
            'extra_rows' => array_values(array_filter([
                $bill->currency !== $bill->company->currency ? [
                    'label' => __(':currency equivalent (rate :rate)', ['currency' => $bill->company->currency, 'rate' => rtrim(rtrim(number_format($bill->exchange_rate, 6), '0'), '.')]),
                    'value' => round($bill->total * $bill->exchange_rate, 2),
                    'currency' => $bill->company->currency,
                ] : null,
                ['label' => __('Paid'), 'value' => $bill->amount_paid],
                ['label' => __('Balance due'), 'value' => $bill->balanceDue()],
            ])),
            'notes' => $bill->notes,
        ];

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $doc,
            'company' => $bill->company,
            'template' => $bill->company->defaultTemplateFor('bill'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$bill->bill_number.'.pdf"',
        ]);
    }

    public function post(Bill $bill, LedgerPostingService $ledger)
    {
        if ($bill->status === 'draft') {
            // The status flip and its journal entry must land together —
            // if postBillPosted() throws after the status update alone had
            // already committed, the bill would read "posted" with no
            // matching GL entry, silently corrupting the books.
            DB::transaction(function () use ($bill, $ledger) {
                $bill->update(['status' => 'posted']);
                $ledger->postBillPosted($bill);
            });

            AuditLog::record('bill.post', $bill, __('Posted bill :number', ['number' => $bill->bill_number]));
        }

        return back()->with('status', __('Bill posted.'));
    }

    public function void(Bill $bill, LedgerPostingService $ledger)
    {
        DB::transaction(function () use ($bill, $ledger) {
            $bill->update(['status' => 'void']);
            $ledger->reverse($bill->company, 'bill', $bill->id, __('Bill :number voided', ['number' => $bill->bill_number]));
        });

        AuditLog::record('bill.void', $bill, __('Voided bill :number', ['number' => $bill->bill_number]));

        return back()->with('status', __('Bill voided.'));
    }

    public function storeAttachment(Request $request, Bill $bill)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        $file = $request->file('file');

        $bill->attachments()->create([
            'company_id' => $bill->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('bill-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(Bill $bill, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === Bill::class && $attachment->attachable_id === $bill->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    private function syncItems(Bill $bill, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new BillItem([
                'bill_id' => $bill->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? TaxRate::defaultRate($bill->company_id),
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
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'currency' => ['nullable', 'string', Rule::in(Currencies::codes())],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}

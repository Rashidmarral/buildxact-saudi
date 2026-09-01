<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Models\WhtRate;
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
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Bill::with('supplier')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('bills.csv', $this->csvHeader(), $query->get()->map(fn ($bill) => $this->csvRow($bill)));
        }

        $bills = $query->paginate($this->resolvePerPage($request))->withQueryString();

        $counts = [
            'all' => Bill::count(),
            'draft' => Bill::where('status', 'draft')->count(),
            'posted' => Bill::where('status', 'posted')->count(),
            'void' => Bill::where('status', 'void')->count(),
        ];

        return view('user.bills.index', compact('bills', 'counts'));
    }

    /**
     * Audit finding MEDIUM-15: no list page could act on more than one
     * record at a time. Exports exactly the checked rows, independent of
     * the current filter.
     */
    public function bulkExport(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $bills = Bill::with('supplier')->whereIn('id', $ids)->orderByDesc('bill_date')->orderByDesc('id')->get();

        return $this->csvResponse('bills-selected.csv', $this->csvHeader(), $bills->map(fn ($bill) => $this->csvRow($bill)));
    }

    /**
     * Void is the only bulk-safe destructive action a bill has — there's
     * no bill delete at all, and void() reverses both stock and the ledger
     * posting, so only currently-posted bills are eligible; an already-
     * void or still-draft bill is skipped rather than double-reversed.
     */
    public function bulkVoid(Request $request, LedgerPostingService $ledger)
    {
        $ids = $this->validatedBulkIds($request);
        $bills = Bill::whereIn('id', $ids)->get();

        $voided = 0;

        foreach ($bills as $bill) {
            if ($bill->status !== 'posted') {
                continue;
            }

            $this->void($bill, $ledger);
            $voided++;
        }

        $skipped = $bills->count() - $voided;

        return back()->with('status', $skipped > 0
            ? __(':voided voided, :skipped skipped — only posted bills can be bulk voided.', ['voided' => $voided, 'skipped' => $skipped])
            : __(':voided bill(s) voided.', ['voided' => $voided]));
    }

    private function validatedBulkIds(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    }

    private function csvHeader(): array
    {
        return [__('Number'), __('Supplier'), __('Date'), __('Total'), __('Balance'), __('Status')];
    }

    private function csvRow(Bill $bill): array
    {
        return [
            $bill->bill_number,
            $bill->supplier->name,
            $bill->bill_date->format('Y-m-d'),
            number_format($bill->total, 2),
            number_format($bill->balanceDue(), 2),
            $bill->status,
        ];
    }

    public function create()
    {
        $company = Auth::user()->company;

        // Lazily backfills the WHT Payable system account + mapping for
        // companies that existed before this feature shipped — mirrors how
        // FixedAssetController backfills its own accounts, since both rely
        // on Account::seedSystemAccounts()/AccountMapping::seedDefaults()
        // being idempotent.
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        WhtRate::seedDefaults($company->id);

        return view('user.bills.form', [
            'bill' => new Bill(['bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'currency' => $company->currency, 'exchange_rate' => 1]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'whtRates' => WhtRate::active()->orderBy('name')->get(),
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

            $supplier = Supplier::findOrFail($data['supplier_id']);

            // WHT only applies to non-resident suppliers under Saudi tax
            // law — a resident supplier's bill never withholds, even if a
            // category was somehow submitted.
            $whtRateId = (! $supplier->is_resident && ! empty($data['wht_rate_id'])) ? $data['wht_rate_id'] : null;

            $bill = Bill::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $company->default_branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'wht_rate_id' => $whtRateId,
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

            if ($whtRateId) {
                $whtBase = (float) $bill->subtotal - (float) $bill->discount_total;
                $bill->wht_amount = round($whtBase * (float) $bill->whtRate->rate / 100, 2);
                $bill->save();
            }

            return $bill;
        });

        AuditLog::record('bill.create', $bill, __('Created bill :number', ['number' => $bill->bill_number]));

        if ($postImmediately) {
            $this->postAndReceiveStock($bill, $ledger);
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
            $this->postAndReceiveStock($bill, $ledger);

            AuditLog::record('bill.post', $bill, __('Posted bill :number', ['number' => $bill->bill_number]));
        }

        return back()->with('status', __('Bill posted.'));
    }

    public function void(Bill $bill, LedgerPostingService $ledger)
    {
        DB::transaction(function () use ($bill, $ledger) {
            if ($bill->stock_received) {
                $this->applyStock($bill, -1);
                $bill->stock_received = false;
            }

            $bill->status = 'void';
            $bill->save();

            $ledger->reverse($bill->company, 'bill', $bill->id, __('Bill :number voided', ['number' => $bill->bill_number]));
        });

        AuditLog::record('bill.void', $bill, __('Voided bill :number', ['number' => $bill->bill_number]));

        return back()->with('status', __('Bill voided.'));
    }

    /**
     * Commercial audit finding: posting a bill capitalized cost to the
     * ledger but never touched physical stock — the only way ItemStock
     * ever increased was a disconnected manual Stock Adjustment. Status
     * flip, ledger posting, and the stock receipt now happen in one
     * transaction, matching how InvoiceController::doSend() deducts stock
     * alongside posting revenue — if any step throws, none of them stick.
     */
    private function postAndReceiveStock(Bill $bill, LedgerPostingService $ledger): void
    {
        DB::transaction(function () use ($bill, $ledger) {
            $bill->update(['status' => 'posted']);
            $ledger->postBillPosted($bill);

            if ($bill->warehouse_id && ! $bill->stock_received) {
                $this->applyStock($bill, 1);
                $bill->update(['stock_received' => true]);
            }
        });
    }

    private function applyStock(Bill $bill, int $direction): void
    {
        $bill->loadMissing('items.item');

        foreach ($bill->items as $line) {
            if (! $line->item || ! $line->item->track_inventory) {
                continue;
            }

            $stock = ItemStock::firstOrCreate(
                ['item_id' => $line->item_id, 'warehouse_id' => $bill->warehouse_id],
                ['quantity' => 0]
            );

            $baseQuantity = $line->item->baseQuantityFor((float) $line->quantity, $line->unit_id);

            $stock->increment('quantity', $baseQuantity * $direction);
        }
    }

    public function storeAttachment(Request $request, Bill $bill)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

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
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'wht_rate_id' => ['nullable', Rule::exists('wht_rates', 'id')->where('company_id', $companyId)],
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

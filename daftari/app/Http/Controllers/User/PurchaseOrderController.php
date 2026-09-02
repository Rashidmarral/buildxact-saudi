<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'purchase-orders.csv',
                [__('Number'), __('Supplier'), __('Order date'), __('Total'), __('Status')],
                $query->get()->map(fn ($order) => [
                    $order->po_number,
                    $order->supplier->name,
                    $order->order_date->format('Y-m-d'),
                    number_format($order->total, 2),
                    $order->status,
                ])
            );
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => PurchaseOrder::count(),
            'draft' => PurchaseOrder::where('status', 'draft')->count(),
            'pending_approval' => PurchaseOrder::where('status', 'pending_approval')->count(),
            'approved' => PurchaseOrder::where('status', 'approved')->count(),
            'partially_billed' => PurchaseOrder::where('status', 'partially_billed')->count(),
            'converted' => PurchaseOrder::where('status', 'converted')->count(),
            'void' => PurchaseOrder::where('status', 'void')->count(),
        ];

        return view('user.purchase-orders.index', compact('orders', 'counts'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.purchase-orders.form', [
            'order' => new PurchaseOrder(['order_date' => now()->toDateString()]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'nextNumberPreview' => $company->po_prefix.'-'.str_pad((string) $company->next_po_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $postImmediately = $request->boolean('post_immediately');

        $order = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $company->default_branch_id,
                'created_by' => Auth::id(),
                'po_number' => $company->nextPoNumber(),
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($order, $data['items']);
            $order->recalculateTotals();

            return $order;
        });

        $status = null;

        if ($postImmediately) {
            if ($order->company->poRequiresApproval((float) $order->total)) {
                $order->update(['status' => 'pending_approval']);
                $this->notifyApprovers($order);
                $status = __('Purchase order created and submitted for approval.');
            } else {
                $order->update(['status' => 'approved']);
                $status = __('Purchase order created and approved.');
            }
        }

        return redirect()->route('app.purchase-orders.show', $order)->with('status', $status ?? __('Purchase order created.'));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('items.billItems.bill', 'supplier', 'bills', 'attachments');

        $template = $purchaseOrder->company->defaultTemplateFor('purchase_order');

        return view('user.purchase-orders.show', ['order' => $purchaseOrder, 'template' => $template]);
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder, MpdfRenderer $renderer)
    {
        $purchaseOrder->loadMissing('items', 'supplier');

        $doc = [
            'type_label' => __('Purchase Order'),
            'type_label_ar' => 'أمر شراء',
            'number' => $purchaseOrder->po_number,
            'date_label' => __('Order date'),
            'date' => $purchaseOrder->order_date,
            'date2_label' => __('Expected'),
            'date2_label_ar' => 'تاريخ التوريد المتوقع',
            'date2' => $purchaseOrder->expected_date,
            'party_label' => __('Supplier'),
            'party_label_ar' => 'المورد',
            'party' => $purchaseOrder->supplier,
            'lines' => $purchaseOrder->items,
            'subtotal' => $purchaseOrder->subtotal,
            'discount_total' => $purchaseOrder->discount_total,
            'vat_total' => $purchaseOrder->vat_total,
            'total' => $purchaseOrder->total,
            'notes' => $purchaseOrder->notes,
        ];

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $doc,
            'company' => $purchaseOrder->company,
            'template' => $purchaseOrder->company->defaultTemplateFor('purchase_order'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$purchaseOrder->po_number.'.pdf"',
        ]);
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        // A plain draft (never crossed the approval threshold) can still
        // be self-approved by anyone with the ordinary "purchases"
        // permission this route already requires — the stricter
        // "approvals" permission only matters once it's actually sitting
        // in pending_approval, waiting on a designated approver.
        if ($purchaseOrder->status === 'pending_approval') {
            abort_unless(Auth::user()->hasPermission('approvals'), 403);

            $purchaseOrder->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            AuditLog::record('purchase_order.approve', $purchaseOrder, __('Approved purchase order :number', ['number' => $purchaseOrder->po_number]));
        } elseif ($purchaseOrder->status === 'draft') {
            $purchaseOrder->update(['status' => 'approved']);
        }

        return back()->with('status', __('Purchase order approved.'));
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(Auth::user()->hasPermission('approvals'), 403);
        abort_unless($purchaseOrder->status === 'pending_approval', 404);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        $purchaseOrder->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        AuditLog::record('purchase_order.reject', $purchaseOrder, __('Rejected purchase order :number', ['number' => $purchaseOrder->po_number]));

        if ($purchaseOrder->created_by) {
            User::find($purchaseOrder->created_by)?->notify(new GenericNotification(
                title: __('Purchase order rejected'),
                body: __('Purchase order :number was rejected.', ['number' => $purchaseOrder->po_number]),
                url: route('app.purchase-orders.show', $purchaseOrder),
                icon: 'purchases',
            ));
        }

        return back()->with('status', __('Purchase order rejected.'));
    }

    public function void(PurchaseOrder $purchaseOrder)
    {
        // Blocked once any bill has been raised against this order
        // (partially or fully billed) — voiding it at that point would
        // leave real bills pointing at a cancelled order.
        if (! in_array($purchaseOrder->status, ['converted', 'partially_billed'], true)) {
            $purchaseOrder->update(['status' => 'void']);
        }

        return back()->with('status', __('Purchase order voided.'));
    }

    /**
     * A purchase order can be delivered — and therefore billed — across
     * several shipments, not just all at once. This shows the remaining
     * (ordered minus already-billed) quantity per line, defaulting the
     * bill quantity to whatever's left; storeBill() below enforces that a
     * bill can never claim more than a line's remaining quantity.
     */
    public function billForm(PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['approved', 'partially_billed'], true)) {
            return back()->withErrors(['purchase_order' => __('This purchase order is not open for billing.')]);
        }

        $purchaseOrder->load('items');

        if ($purchaseOrder->isFullyBilled()) {
            return back()->withErrors(['purchase_order' => __('This purchase order has already been fully billed.')]);
        }

        $company = Auth::user()->company;

        return view('user.purchase-orders.bill-form', [
            'order' => $purchaseOrder,
            'warehouses' => \App\Models\Warehouse::orderBy('name')->get(),
            'nextNumberPreview' => $company->bill_prefix.'-'.str_pad((string) $company->next_bill_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function storeBill(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['approved', 'partially_billed'], true)) {
            return back()->withErrors(['purchase_order' => __('This purchase order is not open for billing.')]);
        }

        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', Rule::exists('purchase_order_items', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $rows = array_values(array_filter($data['items'], fn ($row) => (float) $row['quantity'] > 0));

        if (empty($rows)) {
            return back()->withErrors(['items' => __('Bill at least one line item.')])->withInput();
        }

        $purchaseOrder->load('items');
        $poItemsById = $purchaseOrder->items->keyBy('id');

        foreach ($rows as $row) {
            $poItem = $poItemsById->get((int) $row['purchase_order_item_id']);

            if (! $poItem || (float) $row['quantity'] - $poItem->remainingQuantity() > 0.01) {
                return back()->withErrors(['items' => __('One or more lines bill more than the remaining ordered quantity.')])->withInput();
            }
        }

        $bill = DB::transaction(function () use ($purchaseOrder, $data, $rows) {
            $company = Auth::user()->company;

            $bill = Bill::create([
                'supplier_id' => $purchaseOrder->supplier_id,
                'branch_id' => $purchaseOrder->branch_id ?? $company->default_branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'purchase_order_id' => $purchaseOrder->id,
                'created_by' => Auth::id(),
                'bill_number' => $company->nextBillNumber(),
                'status' => 'draft',
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $company->currency,
                'notes' => $purchaseOrder->notes,
            ]);

            foreach ($rows as $sort => $row) {
                $poItem = $purchaseOrder->items->firstWhere('id', (int) $row['purchase_order_item_id']);

                $line = new BillItem([
                    'bill_id' => $bill->id,
                    'item_id' => $poItem->item_id,
                    'unit_id' => $poItem->unit_id,
                    'purchase_order_item_id' => $poItem->id,
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => $row['vat_rate'],
                    'sort_order' => $sort,
                ]);
                $line->recalculate();
                $line->save();
            }

            $bill->recalculateTotals();

            $purchaseOrder->update([
                'status' => $purchaseOrder->fresh(['items.billItems.bill'])->isFullyBilled() ? 'converted' : 'partially_billed',
                'converted_bill_id' => $bill->id,
            ]);

            return $bill;
        });

        AuditLog::record('purchase_order.bill', $purchaseOrder, __('Billed purchase order :number (:bill)', ['number' => $purchaseOrder->po_number, 'bill' => $bill->bill_number]));

        return redirect()->route('app.bills.show', $bill)->with('status', __('Bill created from purchase order.'));
    }

    public function storeAttachment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');

        $purchaseOrder->attachments()->create([
            'company_id' => $purchaseOrder->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('po-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(PurchaseOrder $purchaseOrder, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === PurchaseOrder::class && $attachment->attachable_id === $purchaseOrder->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    private function notifyApprovers(PurchaseOrder $order): void
    {
        User::where('company_id', $order->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('approvals'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Purchase order awaiting approval'),
                body: __(':number — :amount :currency', ['number' => $order->po_number, 'amount' => number_format($order->total, 2), 'currency' => $order->company->currency]),
                url: route('app.purchase-orders.show', $order),
                icon: 'purchases',
            )));
    }

    private function syncItems(PurchaseOrder $order, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new PurchaseOrderItem([
                'purchase_order_id' => $order->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? TaxRate::defaultRate($order->company_id),
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
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
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

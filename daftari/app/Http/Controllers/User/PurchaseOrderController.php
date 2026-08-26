<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
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
        $purchaseOrder->load('items', 'supplier', 'convertedBill', 'attachments');

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
        if ($purchaseOrder->status !== 'converted') {
            $purchaseOrder->update(['status' => 'void']);
        }

        return back()->with('status', __('Purchase order voided.'));
    }

    public function convertToBill(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'converted') {
            return back()->withErrors(['purchase_order' => __('This purchase order has already been converted.')]);
        }

        if ($purchaseOrder->status === 'pending_approval') {
            return back()->withErrors(['purchase_order' => __('This purchase order is still awaiting approval.')]);
        }

        $bill = DB::transaction(function () use ($purchaseOrder) {
            $company = Auth::user()->company;

            $bill = Bill::create([
                'supplier_id' => $purchaseOrder->supplier_id,
                'branch_id' => $purchaseOrder->branch_id ?? $company->default_branch_id,
                'created_by' => Auth::id(),
                'bill_number' => $company->nextBillNumber(),
                'status' => 'draft',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'discount_total' => $purchaseOrder->discount_total,
                'currency' => $company->currency,
                'notes' => $purchaseOrder->notes,
            ]);

            foreach ($purchaseOrder->items as $sort => $poItem) {
                $bill->items()->create([
                    'item_id' => $poItem->item_id,
                    'unit_id' => $poItem->unit_id,
                    'description' => $poItem->description,
                    'quantity' => $poItem->quantity,
                    'unit_price' => $poItem->unit_price,
                    'vat_rate' => $poItem->vat_rate,
                    'vat_amount' => $poItem->vat_amount,
                    'line_total' => $poItem->line_total,
                    'sort_order' => $sort,
                ]);
            }

            $bill->recalculateTotals();

            $purchaseOrder->update(['status' => 'converted', 'converted_bill_id' => $bill->id]);

            return $bill;
        });

        return redirect()->route('app.bills.show', $bill)->with('status', __('Purchase order converted to bill.'));
    }

    public function storeAttachment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

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

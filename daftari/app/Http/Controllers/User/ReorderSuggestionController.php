<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Audit finding LOW-25: Item::reorder_point already existed and
 * inventory:check-low-stock already notified users when a tracked item
 * dipped to or below it — but the notification just linked to the Stock
 * report; nothing actually turned "this needs reordering" into a
 * purchase order draft. This page lists every item+warehouse pair
 * currently at or below its reorder point (the same query
 * CheckLowStock uses) with a suggested quantity to bring it back up to
 * that point, and turns a selected subset into a real draft PO in one
 * step — the user still picks the supplier and reviews quantities/prices
 * before it's created, this just removes the blank-page starting point.
 */
class ReorderSuggestionController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()->company_id;

        $suggestions = ItemStock::join('items', 'items.id', '=', 'item_stocks.item_id')
            ->where('items.company_id', $companyId)
            ->where('items.track_inventory', true)
            ->where('items.is_active', true)
            ->whereNotNull('items.reorder_point')
            ->whereColumn('item_stocks.quantity', '<=', 'items.reorder_point')
            ->with('item', 'warehouse')
            ->select('item_stocks.*')
            ->orderBy('items.name')
            ->get()
            ->map(fn (ItemStock $stock) => [
                'stock' => $stock,
                'suggested_quantity' => max(0, round((float) $stock->item->reorder_point - (float) $stock->quantity, 2)),
            ])
            ->filter(fn ($row) => $row['suggested_quantity'] > 0)
            ->values();

        return view('user.reorder-suggestions.index', [
            'suggestions' => $suggestions,
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $company->default_branch_id,
                'created_by' => Auth::id(),
                'po_number' => $company->nextPoNumber(),
                'status' => 'draft',
                'order_date' => now()->toDateString(),
                'notes' => __('Generated from low-stock reorder suggestions'),
            ]);

            foreach ($data['items'] as $sort => $row) {
                $line = new PurchaseOrderItem([
                    'purchase_order_id' => $order->id,
                    'item_id' => $row['item_id'],
                    'description' => Item::find($row['item_id'])?->name ?? '',
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => TaxRate::defaultRate($order->company_id),
                    'sort_order' => $sort,
                ]);
                $line->recalculate();
                $line->save();
            }

            $order->recalculateTotals();

            return $order;
        });

        AuditLog::record('purchase_order.create_from_suggestions', $order, __('Created purchase order :number from reorder suggestions', ['number' => $order->po_number]));

        return redirect()->route('app.purchase-orders.show', $order)->with('status', __('Purchase order created from reorder suggestions.'));
    }
}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Audit finding LOW-27: reconciling a physical stock count against the
 * system required opening the single-item Inventory Adjustment modal once
 * per item — with hundreds of tracked items, a real cycle count was
 * impractical. This page lists every tracked item in a chosen warehouse
 * with its current system quantity, lets the user key in what was
 * actually counted, and on submit records one StockAdjustment per item
 * whose count differs from the system quantity (increase or decrease,
 * whichever direction closes the gap) — reusing the exact posting path
 * the single-item flow already uses, just batched.
 */
class PhysicalCountController extends Controller
{
    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $warehouses = Warehouse::orderBy('name')->get();
        $warehouseId = $request->integer('warehouse_id') ?: $warehouses->first()?->id;
        $warehouse = $warehouseId ? $warehouses->firstWhere('id', $warehouseId) : null;

        $rows = collect();

        if ($warehouse) {
            $rows = Item::where('company_id', $companyId)
                ->where('track_inventory', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Item $item) => [
                    'item' => $item,
                    'system_quantity' => (float) (ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity') ?? 0),
                ]);
        }

        return view('user.physical-counts.index', [
            'warehouses' => $warehouses,
            'warehouse' => $warehouse,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $adjusted = DB::transaction(function () use ($data, $ledger) {
            $count = 0;

            foreach ($data['items'] as $row) {
                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $row['item_id'], 'warehouse_id' => $data['warehouse_id']],
                    ['quantity' => 0]
                );

                $delta = round((float) $row['counted_quantity'] - (float) $stock->quantity, 2);

                if (abs($delta) < 0.01) {
                    continue;
                }

                $adjustment = StockAdjustment::create([
                    'item_id' => $row['item_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'created_by' => Auth::id(),
                    'type' => $delta > 0 ? 'increase' : 'decrease',
                    'quantity' => abs($delta),
                    'reason' => __('Physical inventory count'),
                    'date' => now()->toDateString(),
                    'status' => 'recorded',
                ]);

                $stock->increment('quantity', $delta);
                $ledger->postStockAdjustment($adjustment);
                $count++;
            }

            return $count;
        });

        if ($adjusted > 0) {
            AuditLog::record('stock_adjustment.physical_count', null, __(':count item(s) adjusted from a physical inventory count', ['count' => $adjusted]));
        }

        return redirect()->route('app.physical-counts.index', ['warehouse_id' => $data['warehouse_id']])
            ->with('status', $adjusted > 0
                ? __(':count item(s) adjusted to match the count.', ['count' => $adjusted])
                : __('No differences found — nothing to adjust.'));
    }
}

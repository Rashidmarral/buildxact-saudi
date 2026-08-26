<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        return view('user.stock-transfers.index', [
            'transfers' => StockTransfer::with('item', 'fromWarehouse', 'toWarehouse')->latest('date')->latest('id')->paginate(20),
            'items' => Item::where('track_inventory', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $transfer = DB::transaction(function () use ($data) {
            $fromStock = ItemStock::firstOrCreate(
                ['item_id' => $data['item_id'], 'warehouse_id' => $data['from_warehouse_id']],
                ['quantity' => 0]
            );

            if ((float) $fromStock->quantity < (float) $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :available in stock at the source warehouse.', ['available' => rtrim(rtrim(number_format($fromStock->quantity, 2), '0'), '.')]),
                ]);
            }

            $transfer = StockTransfer::create([
                'item_id' => $data['item_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'created_by' => Auth::id(),
                'quantity' => $data['quantity'],
                'date' => now()->toDateString(),
                'status' => 'recorded',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyToStock($transfer, 1);

            return $transfer;
        });

        AuditLog::record('stock_transfer.create', $transfer, __('Transferred :qty of :item from :from to :to', [
            'qty' => rtrim(rtrim(number_format($transfer->quantity, 2), '0'), '.'),
            'item' => $transfer->item->name,
            'from' => $transfer->fromWarehouse->name,
            'to' => $transfer->toWarehouse->name,
        ]));

        return redirect()->route('app.stock-transfers.index')->with('status', __('Stock transfer recorded.'));
    }

    public function reverse(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === 'reversed') {
            return back();
        }

        DB::transaction(function () use ($stockTransfer) {
            $toStock = ItemStock::firstOrCreate(
                ['item_id' => $stockTransfer->item_id, 'warehouse_id' => $stockTransfer->to_warehouse_id],
                ['quantity' => 0]
            );

            if ((float) $toStock->quantity < (float) $stockTransfer->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Cannot reverse — the destination warehouse no longer holds enough of this item (some may have since been sold or moved again).'),
                ]);
            }

            $this->applyToStock($stockTransfer, -1);
            $stockTransfer->update(['status' => 'reversed']);
        });

        AuditLog::record('stock_transfer.reverse', $stockTransfer, __('Reversed stock transfer of :item', ['item' => $stockTransfer->item->name]));

        return back()->with('status', __('Stock transfer reversed.'));
    }

    /**
     * $direction 1 moves quantity from -> to (the original transfer);
     * -1 undoes it by moving the same quantity back to -> from.
     */
    private function applyToStock(StockTransfer $transfer, int $direction): void
    {
        $fromWarehouseId = $direction === 1 ? $transfer->from_warehouse_id : $transfer->to_warehouse_id;
        $toWarehouseId = $direction === 1 ? $transfer->to_warehouse_id : $transfer->from_warehouse_id;

        $fromStock = ItemStock::firstOrCreate(['item_id' => $transfer->item_id, 'warehouse_id' => $fromWarehouseId], ['quantity' => 0]);
        $toStock = ItemStock::firstOrCreate(['item_id' => $transfer->item_id, 'warehouse_id' => $toWarehouseId], ['quantity' => 0]);

        $fromStock->decrement('quantity', $transfer->quantity);
        $toStock->increment('quantity', $transfer->quantity);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'from_warehouse_id' => ['required', 'different:to_warehouse_id', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'to_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

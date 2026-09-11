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

        // Two-step transfer: stock leaves the source warehouse immediately
        // (so it stops showing as available there) but doesn't land in the
        // destination warehouse's stock until someone at that end confirms
        // receive() — matching how a transfer between two physical
        // locations actually works, instead of teleporting instantly.
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
                'status' => 'in_transit',
                'notes' => $data['notes'] ?? null,
            ]);

            $fromStock->decrement('quantity', $transfer->quantity);

            return $transfer;
        });

        AuditLog::record('stock_transfer.create', $transfer, __('Transferred :qty of :item from :from to :to (in transit)', [
            'qty' => rtrim(rtrim(number_format($transfer->quantity, 2), '0'), '.'),
            'item' => $transfer->item->name,
            'from' => $transfer->fromWarehouse->name,
            'to' => $transfer->toWarehouse->name,
        ]));

        return redirect()->route('app.stock-transfers.index')->with('status', __('Stock transfer recorded — in transit to the destination warehouse.'));
    }

    /**
     * Confirms the quantity actually arrived at the destination warehouse,
     * adding it to that warehouse's stock. Only valid while in_transit —
     * a completed or reversed transfer has nothing left to receive.
     */
    public function receive(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'in_transit') {
            return back();
        }

        DB::transaction(function () use ($stockTransfer) {
            $toStock = ItemStock::firstOrCreate(
                ['item_id' => $stockTransfer->item_id, 'warehouse_id' => $stockTransfer->to_warehouse_id],
                ['quantity' => 0]
            );

            $toStock->increment('quantity', $stockTransfer->quantity);
            $stockTransfer->update(['status' => 'completed', 'received_at' => now(), 'received_by' => Auth::id()]);
        });

        AuditLog::record('stock_transfer.receive', $stockTransfer, __('Received :qty of :item at :to', [
            'qty' => rtrim(rtrim(number_format($stockTransfer->quantity, 2), '0'), '.'),
            'item' => $stockTransfer->item->name,
            'to' => $stockTransfer->toWarehouse->name,
        ]));

        return back()->with('status', __('Stock transfer received.'));
    }

    public function reverse(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === 'reversed') {
            return back();
        }

        DB::transaction(function () use ($stockTransfer) {
            $fromStock = ItemStock::firstOrCreate(
                ['item_id' => $stockTransfer->item_id, 'warehouse_id' => $stockTransfer->from_warehouse_id],
                ['quantity' => 0]
            );

            if ($stockTransfer->status === 'completed') {
                // The quantity already landed at the destination — undo
                // both legs, same as before receive() existed.
                $toStock = ItemStock::firstOrCreate(
                    ['item_id' => $stockTransfer->item_id, 'warehouse_id' => $stockTransfer->to_warehouse_id],
                    ['quantity' => 0]
                );

                if ((float) $toStock->quantity < (float) $stockTransfer->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => __('Cannot reverse — the destination warehouse no longer holds enough of this item (some may have since been sold or moved again).'),
                    ]);
                }

                $toStock->decrement('quantity', $stockTransfer->quantity);
            }

            // While in_transit, the quantity only ever left the source —
            // it was never added to the destination, so reversing it is
            // just giving the source warehouse its stock back.
            $fromStock->increment('quantity', $stockTransfer->quantity);
            $stockTransfer->update(['status' => 'reversed']);
        });

        AuditLog::record('stock_transfer.reverse', $stockTransfer, __('Reversed stock transfer of :item', ['item' => $stockTransfer->item->name]));

        return back()->with('status', __('Stock transfer reversed.'));
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

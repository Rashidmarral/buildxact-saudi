<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemLot;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Audit finding LOW-23: items had a single item-level expiry_date and no
 * way to tell one physical batch/serial apart from another of the same
 * item — every unit in a warehouse was fungible. Item::tracking_type
 * ('lot' or 'serial') opts an item into this; each ItemLot is a traceable
 * slice of that item+warehouse's ItemStock (its own lot/serial number and
 * expiry), and receiving/consuming a lot keeps the two in sync rather
 * than the lot table becoming a second, disconnected stock figure.
 */
class ItemLotController extends Controller
{
    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $lots = ItemLot::with('item', 'warehouse')
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->query('item_id')))
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('user.item-lots.index', [
            'lots' => $lots,
            'items' => Item::where('company_id', $companyId)->whereIn('tracking_type', ['lot', 'serial'])->orderBy('name')->get(),
            'warehouses' => Warehouse::where('company_id', $companyId)->orderBy('name')->get(),
            'selectedItemId' => $request->integer('item_id'),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'lot_number' => ['required', 'string', 'max:60'],
            'serial_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $item = Item::findOrFail($data['item_id']);

        if (! $item->isLotOrSerialTracked()) {
            throw ValidationException::withMessages(['item_id' => __('This item is not set up for lot/serial tracking. Enable it on the item first.')]);
        }

        if ($item->tracking_type === 'serial') {
            if (empty($data['serial_number'])) {
                throw ValidationException::withMessages(['serial_number' => __('A serial number is required for a serial-tracked item.')]);
            }
            if ((float) $data['quantity'] !== 1.0) {
                throw ValidationException::withMessages(['quantity' => __('A serial-tracked lot always represents exactly one unit.')]);
            }
        }

        $lot = DB::transaction(function () use ($data) {
            $lot = ItemLot::create([
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'lot_number' => $data['lot_number'],
                'serial_number' => $data['serial_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'quantity' => $data['quantity'],
                'received_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $stock = ItemStock::firstOrCreate(
                ['item_id' => $data['item_id'], 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $lot->quantity);

            return $lot;
        });

        AuditLog::record('item_lot.receive', $lot, __('Received :qty of :item as lot :lot', [
            'qty' => rtrim(rtrim(number_format($lot->quantity, 2), '0'), '.'),
            'item' => $lot->item->name,
            'lot' => $lot->lot_number,
        ]));

        return back()->with('status', __('Lot received.'));
    }

    public function consume(Request $request, ItemLot $itemLot)
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ((float) $data['quantity'] > (float) $itemLot->quantity) {
            throw ValidationException::withMessages(['quantity' => __('Only :available remaining in this lot.', ['available' => rtrim(rtrim(number_format($itemLot->quantity, 2), '0'), '.')])]);
        }

        DB::transaction(function () use ($itemLot, $data) {
            $stock = ItemStock::firstOrCreate(
                ['item_id' => $itemLot->item_id, 'warehouse_id' => $itemLot->warehouse_id],
                ['quantity' => 0]
            );

            if ((float) $stock->quantity < (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => __('The warehouse no longer holds enough of this item to consume from this lot.')]);
            }

            $stock->decrement('quantity', $data['quantity']);
            $itemLot->decrement('quantity', $data['quantity']);
        });

        AuditLog::record('item_lot.consume', $itemLot, __('Consumed :qty of :item from lot :lot', [
            'qty' => rtrim(rtrim(number_format($data['quantity'], 2), '0'), '.'),
            'item' => $itemLot->item->name,
            'lot' => $itemLot->lot_number,
        ]));

        return back()->with('status', __('Lot quantity consumed.'));
    }
}

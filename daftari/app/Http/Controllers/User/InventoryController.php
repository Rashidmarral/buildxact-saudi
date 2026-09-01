<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ResolvesPerPage;

    public function stock(Request $request)
    {
        $stocks = ItemStock::with('item', 'warehouse')
            ->whereHas('item', fn ($q) => $q->where('track_inventory', true))
            ->orderBy(Item::select('name')->whereColumn('items.id', 'item_stocks.item_id'))
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.inventory.stock', compact('stocks'));
    }

    public function valuation()
    {
        $stocks = ItemStock::with('item', 'warehouse')
            ->whereHas('item', fn ($q) => $q->where('track_inventory', true))
            ->get()
            ->sortBy(fn ($stock) => $stock->item->name)
            ->map(fn ($stock) => [
                'item' => $stock->item,
                'warehouse' => $stock->warehouse,
                'quantity' => (float) $stock->quantity,
                'avg_cost' => (float) ($stock->item->purchase_price ?? 0),
                'total_value' => (float) $stock->quantity * (float) ($stock->item->purchase_price ?? 0),
            ]);

        return view('user.inventory.valuation', [
            'rows' => $stocks,
            'grandTotal' => $stocks->sum('total_value'),
        ]);
    }
}

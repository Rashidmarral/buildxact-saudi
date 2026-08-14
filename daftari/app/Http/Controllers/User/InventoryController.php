<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;

class InventoryController extends Controller
{
    public function stock()
    {
        $stocks = ItemStock::with('item', 'warehouse')
            ->whereHas('item', fn ($q) => $q->where('track_inventory', true))
            ->get()
            ->sortBy(fn ($stock) => $stock->item->name);

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

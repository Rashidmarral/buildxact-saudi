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
}

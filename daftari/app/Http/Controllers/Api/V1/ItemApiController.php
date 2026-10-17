<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemApiController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::orderBy('name')->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($items->through(fn (Item $item) => $this->transform($item)));
    }

    public function show(Item $item)
    {
        return response()->json($this->transform($item));
    }

    private function transform(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'name_ar' => $item->name_ar,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'category' => $item->category,
            'unit' => $item->unit,
            'unit_code' => $item->unit_code,
            'unit_price' => $item->unit_price,
            'vat_rate' => $item->vat_rate,
            'is_active' => $item->is_active,
            'track_inventory' => $item->track_inventory,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->paginate(20);

        return view('user.items.index', compact('items'));
    }

    public function create()
    {
        return view('user.items.form', ['item' => new Item]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Item::create($data);

        return redirect()->route('app.items.index')->with('status', __('Item saved.'));
    }

    public function edit(Item $item)
    {
        return view('user.items.form', compact('item'));
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validated($request);
        $item->update($data);

        return redirect()->route('app.items.index')->with('status', __('Item updated.'));
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('app.items.index')->with('status', __('Item deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:40'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['track_inventory'] = $request->boolean('track_inventory');

        return $data;
    }
}

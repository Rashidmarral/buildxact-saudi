<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        $data = $this->withImage($request, $data);
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
        $data = $this->withImage($request, $data, $item);
        $item->update($data);

        return redirect()->route('app.items.index')->with('status', __('Item updated.'));
    }

    public function destroy(Item $item)
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();

        return redirect()->route('app.items.index')->with('status', __('Item deleted.'));
    }

    public function generateBarcode()
    {
        do {
            $barcode = (string) random_int(100000000000, 999999999999);
        } while (Item::where('barcode', $barcode)->exists());

        return response()->json(['barcode' => $barcode]);
    }

    private function withImage(Request $request, array $data, ?Item $item = null): array
    {
        if ($request->hasFile('image')) {
            if ($item?->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $data['image_path'] = $request->file('image')->store('items', 'public');
        }

        return $data;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:40'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'item_type' => ['required', 'in:service,physical'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_code' => ['required', Rule::in(array_keys(Item::UNIT_CODES))],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['track_inventory'] = $data['item_type'] === 'physical' && $request->boolean('track_inventory');

        return $data;
    }
}

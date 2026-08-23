<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return view('user.items.form', [
            'item' => new Item,
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $altUnits = $data['alt_units'] ?? [];
        unset($data['alt_units']);
        $data = $this->withImage($request, $data);
        $item = Item::create($data);
        $this->syncAltUnits($item, $altUnits);

        return redirect()->route('app.items.index')->with('status', __('Item saved.'));
    }

    public function edit(Item $item)
    {
        return view('user.items.form', [
            'item' => $item,
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validated($request);
        $altUnits = $data['alt_units'] ?? [];
        unset($data['alt_units']);
        $data = $this->withImage($request, $data, $item);
        $item->update($data);
        $this->syncAltUnits($item, $altUnits);

        return redirect()->route('app.items.index')->with('status', __('Item updated.'));
    }

    private function syncAltUnits(Item $item, array $altUnits): void
    {
        $item->itemUnits()->delete();

        foreach ($altUnits as $row) {
            if (empty($row['unit_id']) || (int) $row['unit_id'] === $item->base_unit_id) {
                continue;
            }

            ItemUnit::create([
                'item_id' => $item->id,
                'unit_id' => $row['unit_id'],
                'conversion_factor' => $row['conversion_factor'] ?? 1,
                'unit_price' => ($row['unit_price'] ?? '') !== '' ? $row['unit_price'] : null,
            ]);
        }
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
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sku' => ['nullable', 'string', 'max:40'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'item_type' => ['required', 'in:service,physical'],
            'base_unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'alt_units' => ['nullable', 'array'],
            'alt_units.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'alt_units.*.conversion_factor' => ['nullable', 'numeric', 'min:0.0001'],
            'alt_units.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['track_inventory'] = $data['item_type'] === 'physical' && $request->boolean('track_inventory');

        $baseUnit = ! empty($data['base_unit_id']) ? Unit::find($data['base_unit_id']) : null;
        $data['unit'] = $baseUnit->name ?? 'unit';
        $data['unit_code'] = $baseUnit ? ($baseUnit->code ?: 'PCE') : 'PCE';

        return $data;
    }
}

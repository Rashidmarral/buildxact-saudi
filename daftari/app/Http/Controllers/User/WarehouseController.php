<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::withCount('stocks')->orderBy('name')->get();

        return view('user.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('user.warehouses.form', ['warehouse' => new Warehouse]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $warehouse = Warehouse::create($data);

        if (! Warehouse::where('is_default', true)->where('id', '!=', $warehouse->id)->exists()) {
            $warehouse->update(['is_default' => true]);
        }

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse saved.'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('user.warehouses.form', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->validated($request));

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse updated.'));
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->withErrors(['warehouse' => __('This warehouse still holds stock and cannot be deleted.')]);
        }

        $warehouse->delete();

        return redirect()->route('app.warehouses.index')->with('status', __('Warehouse deleted.'));
    }

    public function makeDefault(Warehouse $warehouse)
    {
        Warehouse::where('company_id', Auth::user()->company_id)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);

        return back()->with('status', __('Default warehouse updated.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

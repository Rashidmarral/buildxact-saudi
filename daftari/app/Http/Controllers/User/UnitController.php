<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $baseItemCounts = Item::selectRaw('base_unit_id, count(*) as aggregate')
            ->whereNotNull('base_unit_id')
            ->groupBy('base_unit_id')
            ->pluck('aggregate', 'base_unit_id');

        return view('user.units.index', [
            'units' => Unit::withCount('itemUnits')
                ->orderBy('name')
                ->get()
                ->each(function (Unit $unit) use ($baseItemCounts) {
                    $unit->base_items_count = $baseItemCounts->get($unit->id, 0);
                }),
            'unitCodes' => Item::UNIT_CODES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Unit::create($data);

        return redirect()->route('app.units.index')->with('status', __('Unit saved.'));
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $this->validated($request);

        $unit->update($data);

        return redirect()->route('app.units.index')->with('status', __('Unit updated.'));
    }

    public function destroy(Unit $unit)
    {
        if (Item::where('base_unit_id', $unit->id)->exists() || $unit->itemUnits()->exists()) {
            return back()->withErrors(['unit' => __('This unit is in use by one or more items and cannot be deleted.')]);
        }

        $unit->delete();

        return redirect()->route('app.units.index')->with('status', __('Unit deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);
    }
}
